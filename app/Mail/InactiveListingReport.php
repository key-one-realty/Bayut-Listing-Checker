<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InactiveListingReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected $inactive_listings_data;
    /**
     * Create a new message instance.
     */
    public function __construct(array $inactive_listings_data)
    {
        //
        $this->inactive_listings_data = $inactive_listings_data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Inactive Listing Report',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.listings.inactive',
            with: [
                "no_of_feeds_submitted" => $this->inactive_listings_data["no_of_feeds_submitted"],
                "no_of_inactive_feeds" => $this->inactive_listings_data["no_of_inactive_feeds"],
                "inactive_listings_report" => $this->inactive_listings_data["inactive_listings_report"]
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
