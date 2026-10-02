<?php

namespace App\Mail;

use App\Models\Candidature;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CandidatureNotificationAdmin extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Candidature $candidature)
    {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->candidature->email, "{$this->candidature->prenom} {$this->candidature->nom}")],
            subject: "Nouvelle candidature – {$this->candidature->prenom} {$this->candidature->nom}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.candidature-notification-admin',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $extension = pathinfo($this->candidature->cv, PATHINFO_EXTENSION);

        return [
            Attachment::fromStorageDisk('public', $this->candidature->cv)
                ->as("CV-{$this->candidature->prenom}-{$this->candidature->nom}.{$extension}"),
        ];
    }
}
