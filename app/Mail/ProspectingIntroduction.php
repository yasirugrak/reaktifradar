<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ProspectingIntroduction extends Mailable
{
    public const SUBJECT = 'Enerjisa faturanızda ceza sürprizi yaşamayın';

    public function __construct(public bool $test = true) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->test ? '[TEST] ' : '').self::SUBJECT);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.prospecting-introduction', text: 'mail.prospecting-introduction-text', with: [
            'website' => 'https://reaktifradar.com',
        ]);
    }
}
