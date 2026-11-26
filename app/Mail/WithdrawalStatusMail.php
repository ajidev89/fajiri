<?php

namespace App\Mail;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WithdrawalStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WithdrawalRequest $withdrawal,
        public string $heading,
        public string $body,
        public bool $forAdmin = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.withdrawal-status');
    }
}
