<?php

namespace App\Mail;

use App\Models\CallbackRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CallbackRequested extends Mailable
{
    public function __construct(public CallbackRequest $callback) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ReaktifRadar · Yeni aranma talebi');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.callback-requested', text: 'mail.callback-requested-text', with: [
            'url' => rtrim((string) config('app.url'), '/').'/admin/callback-requests',
        ]);
    }
}
