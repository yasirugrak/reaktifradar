<?php

namespace Tests\Feature;

use App\Access\PanelRole;
use App\Filament\Resources\CallbackRequestResource;
use App\Filament\Resources\CallbackRequestResource\Pages\ListCallbackRequests;
use App\Models\CallbackRequest;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_callback_persists_without_mail_and_cannot_set_status(): void
    {
        $this->post('/iletisim/geri-arama', ['name' => 'Test Yetkili', 'phone' => '0555 123 45 67',
            'company' => 'Örnek işletme', 'contact_permission' => '1', 'status' => 'closed'])
            ->assertRedirect('/#iletisim')->assertSessionHas('callback_success');
        $this->assertDatabaseHas('callback_requests', ['name' => 'Test Yetkili', 'status' => 'new']);
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
