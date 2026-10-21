<?php

namespace Tests\Feature;

use App\Access\PanelRole;
use App\Filament\Pages\Prospecting;
use App\Jobs\RunProspectScan;
use App\Models\ProspectContact;
use App\Models\ProspectScan;
use App\Models\User;
use App\Services\ProspectCrawler;
use App\Services\ProspectSearch;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ProspectingTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_and_actions_and_export(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => PanelRole::SuperAdmin, 'email_verified_at' => now()]));
        $this->get(Prospecting::getUrl())->assertOk()->assertSee('Firma ve iletişim listesi');
        config(['prospecting.google_key' => 'test-secret', 'prospecting.google_cx' => 'test-engine']);
        Queue::fake();
        $page = Livewire::test(Prospecting::class)->set('region', 'Kocaeli')->set('osb', 'Gebze OSB')->call('start')->assertHasNoErrors();
        $scan = ProspectScan::firstOrFail();
        Queue::assertPushed(RunProspectScan::class);
        $contact = ProspectContact::create(['prospect_scan_id' => $scan->id, 'company' => 'Firma', 'email' => 'info@firma.test', 'source_url' => 'https://firma.test/iletisim']);
        $page->call('review', $contact->id, 'approved')->assertHasNoErrors()->call('export')->assertFileDownloaded('firma-eposta-'.$scan->id.'.csv');
        $page->call('cancel');
        $this->assertSame('cancelled', $scan->fresh()->status);
        $page->call('restartScan', $scan->id)->assertHasNoErrors();
        $newScan = ProspectScan::latest('id')->firstOrFail();
        $this->assertNotSame($scan->id, $newScan->id);
        $this->assertSame($scan->region, $newScan->region);
        $this->assertSame($scan->osb, $newScan->osb);
        Queue::assertPushed(RunProspectScan::class, fn ($job) => $job->scanId === $newScan->id);
        $page->call('deleteScan', $newScan->id);
        $this->assertModelExists($newScan);
        $page->call('selectScan', $scan->id)->call('deleteScan', $scan->id)->assertSet('scanId', null);
        $this->assertModelMissing($scan);
        $this->assertModelMissing($contact);

        $this->actingAs(User::factory()->create(['role' => PanelRole::Support]));
        $this->get(Prospecting::getUrl())->assertForbidden();
    }

    public function test_job_discovers_and_collects_unique_contacts(): void
    {
        Queue::fake();
        $scan = ProspectScan::create(['region' => 'Kocaeli', 'limit' => 20]);
        $search = $this->mock(ProspectSearch::class);
        $search->shouldReceive('discover')->once()->andReturn([['company' => 'Firma', 'url' => 'https://firma.test']]);
        $crawler = $this->mock(ProspectCrawler::class)->makePartial();
        $crawler->shouldReceive('fetch')->once()->andReturn('info@firma.test info@firma.test ceo@firma.test');
        $crawler->shouldReceive('contactLinks')->once()->andReturn([]);
        $job = new RunProspectScan($scan->id);
        $job->handle($search, $crawler);
        $job->handle($search, $crawler);
        $this->assertDatabaseCount('prospect_contacts', 1);
        $this->assertSame('completed', $scan->fresh()->status);
        $job->handle($search, $crawler);
        $this->assertDatabaseCount('prospect_contacts', 1);
    }

    public function test_bulk_mail_only_queues_approved_once_and_rechecks_exclusions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => PanelRole::SuperAdmin, 'email_verified_at' => now()]));
        Queue::fake();
        \Illuminate\Support\Facades\Mail::fake();
        $scan = ProspectScan::create(['region' => 'Ankara', 'limit' => 20]);
        foreach (['approved', 'review', 'excluded'] as $status) {
            ProspectContact::create(['prospect_scan_id' => $scan->id, 'company' => 'Firma', 'email' => $status.'@firma.test', 'source_url' => 'https://firma.test', 'status' => $status]);
        }
        $page = Livewire::test(Prospecting::class)->set('scanId', $scan->id);
        $page->call('sendApproved')->call('sendApproved');
        $this->assertDatabaseCount('prospect_mail_deliveries', 1);
        Queue::assertPushed(\App\Jobs\SendProspectIntroduction::class, 1);
        $delivery = \App\Models\ProspectMailDelivery::firstOrFail();
        $job = new \App\Jobs\SendProspectIntroduction($delivery->id);
        ProspectContact::whereKey($delivery->prospect_contact_id)->update(['status' => 'excluded']);
        $job->handle();
        $this->assertSame('cancelled', $delivery->fresh()->status);
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }
}
