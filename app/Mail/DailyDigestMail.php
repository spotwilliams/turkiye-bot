<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $todayCount,
        public int $tomorrowCount,
        public int $weekCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your school day briefing',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.daily-digest',
            with: [
                'todayCount' => $this->todayCount,
                'tomorrowCount' => $this->tomorrowCount,
                'weekCount' => $this->weekCount,
            ],
        );
    }
}
