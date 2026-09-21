<?php

namespace Tests\Feature;

use App\Access\PanelRole;
use App\Filament\Resources\CallbackRequestResource;
use App\Filament\Resources\CallbackRequestResource\Pages\ListCallbackRequests;
use App\Mail\CallbackRequested;
use App\Models\CallbackRequest;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_uses_contact_settings_and_new_panel_url(): void
    {
        config(['contact.phone' => '+90 555 123 45 67', 'contact.email' => 'hello@example.test']);
        $this->get('/')->assertOk()->assertSee('tel:+905551234567', false)
            ->assertSee('mailto:hello@example.test', false)->assertDontSee('/panel/login', false)->assertDontSee('Müşteri girişi')
            ->assertSee('Reaktif cezayı')->assertSee('Beni arayın')->assertDontSee('Enerjisa')->assertDontSee('BEDAŞ');
        $this->get('/panel')->assertRedirect('/panel/login');
        $this->get('/admin/login')->assertOk();
        $this->get('/enerjisa/reactive?period=daily')->assertRedirect('/panel/reactive?period=daily');
    }

    public function test_callback_sends_to_owner_and_cannot_set_status(): void
    {
        Mail::fake();
        config(['contact.email' => 'owner@example.test', 'contact.notification_email' => null]);
        $this->post('/iletisim/geri-arama', ['name' => 'Test Yetkili', 'phone' => '0555 123 45 67',
            'company' => 'Örnek işletme', 'contact_permission' => '1', 'status' => 'closed'])
            ->assertRedirect('/#iletisim')->assertSessionHas('callback_success');
        $this->assertDatabaseHas('callback_requests', ['name' => 'Test Yetkili', 'status' => 'new']);
        Mail::assertSent(CallbackRequested::class, fn ($mail) => $mail->hasTo('owner@example.test') && $mail->mailer === 'smtp' && $mail->callback->phone === '0555 123 45 67');
        Mail::assertSentCount(1);
    }

    public function test_smtp_failure_keeps_request_and_success_response(): void
    {
        config(['contact.email' => 'public@example.test', 'contact.notification_email' => 'owner@example.test']);
        Mail::shouldReceive('mailer')->with('smtp')->once()->andReturnSelf();
        Mail::shouldReceive('to')->with('owner@example.test')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('private smtp password'));
        Log::shouldReceive('warning')->once()->with('Callback notification could not be sent; request remains in admin panel.', \Mockery::on(fn ($context) => array_keys($context) === ['callback_request_id']));
        $this->post('/iletisim/geri-arama', ['name' => 'Test', 'phone' => '05551234567', 'contact_permission' => '1'])
            ->assertRedirect('/#iletisim')->assertSessionHas('callback_success');
        $this->assertDatabaseCount('callback_requests', 1);
    }

    public function test_callback_email_escapes_visitor_content(): void
    {
        $callback = CallbackRequest::create(['name' => '<script>alert(1)</script>', 'phone' => '05551234567', 'company' => 'A & B']);
        $html = (new CallbackRequested($callback))->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('A &amp; B', $html);
        $this->assertStringContainsString('/admin/callback-requests', $html);
    }

    public function test_callback_rejects_invalid_phone_missing_consent_and_bot_field(): void
    {
        $this->post('/iletisim/geri-arama', ['name' => 'Test', 'phone' => '----------', 'website' => 'spam'])
            ->assertSessionHasErrors(['phone', 'contact_permission', 'website']);
        $this->assertDatabaseCount('callback_requests', 0);
    }

    public function test_callback_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/iletisim/geri-arama', [])->assertStatus(302);
        }
        $this->post('/iletisim/geri-arama', [])->assertStatus(429);
    }

    public function test_only_admin_can_manage_callback_requests(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $record = CallbackRequest::create(['name' => 'Test', 'phone' => '05551234567']);
        $this->get(CallbackRequestResource::getUrl())->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['role' => PanelRole::Support, 'email_verified_at' => now()]));
        $this->get(CallbackRequestResource::getUrl())->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => PanelRole::SuperAdmin, 'email_verified_at' => now()]));
        $this->get(CallbackRequestResource::getUrl())->assertOk()->assertSee('Aranma Talepleri');
        Livewire::test(ListCallbackRequests::class)->callTableAction(EditAction::class, $record, data: ['status' => 'contacted'])->assertHasNoTableActionErrors();
        $this->assertSame('contacted', $record->fresh()->status);
    }
}
