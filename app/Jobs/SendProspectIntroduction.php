<?php

namespace App\Jobs;

use App\Mail\ProspectingIntroduction;
use App\Models\ProspectContact;
use App\Models\ProspectMailDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendProspectIntroduction implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public int $deliveryId)
    {
        $this->onConnection('database');
    }

    public function handle(): void
    {
        // Atomic claim prevents duplicate jobs from sending the same message twice.
        if (! ProspectMailDelivery::whereKey($this->deliveryId)->where('status', 'queued')->update(['status' => 'sending'])) {
            return;
        }
        $delivery = ProspectMailDelivery::findOrFail($this->deliveryId);
        $contact = ProspectContact::find($delivery->prospect_contact_id);
        if (! $contact || $contact->status !== 'approved' || strtolower(trim($contact->email)) !== $delivery->email
            || ProspectContact::whereRaw('LOWER(email) = ?', [$delivery->email])->where('status', 'excluded')->exists()) {
            $delivery->update(['status' => 'cancelled']);
            return;
        }
        try {
            Mail::mailer('smtp')->to($delivery->email)->send(new ProspectingIntroduction(test: false));
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable) {
            // SMTP acceptance may be uncertain; never automatically resend.
            $delivery->update(['status' => 'failed']);
        }
    }

    public function failed(?Throwable $exception): void
    {
        ProspectMailDelivery::whereKey($this->deliveryId)->whereIn('status', ['queued', 'sending'])->update(['status' => 'failed']);
    }
}
