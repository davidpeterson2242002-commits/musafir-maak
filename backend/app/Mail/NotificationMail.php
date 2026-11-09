<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Plain transactional email used by the Notifier's email channel (spec 5.14). */
class NotificationMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $title, public readonly string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title.' — مسافر معاك');
    }

    public function content(): Content
    {
        $body = nl2br(e($this->body));

        return new Content(htmlString: <<<HTML
            <div dir="rtl" style="font-family: Tahoma, Arial, sans-serif; font-size: 15px; line-height: 1.7; color: #222;">
                <h2 style="margin: 0 0 12px;">{$this->e($this->title)}</h2>
                <p>{$body}</p>
                <p style="color: #888; font-size: 12px;">مسافر معاك</p>
            </div>
            HTML);
    }

    private function e(string $value): string
    {
        return e($value);
    }
}
