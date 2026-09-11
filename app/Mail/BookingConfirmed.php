<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{room_type: string, room_number: string}>  $rooms
     */
    public function __construct(
        public string $guestName,
        public string $bookingReference,
        public array $rooms,
        public string $checkInDate,
        public string $checkOutDate,
        public int $nights,
        public float $totalAmount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Booking Confirmed - {$this->bookingReference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking-confirmed',
        );
    }
}
