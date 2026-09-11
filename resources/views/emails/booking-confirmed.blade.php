<x-mail::message>
# Booking Confirmed

Hi {{ $guestName }}, your stay at The Matrix Hotel is confirmed. Here are your details:

<x-mail::panel>
**Booking Reference:** {{ $bookingReference }}
</x-mail::panel>

<x-mail::table>
| | |
|:--|--:|
@foreach ($rooms as $room)
| Room | {{ $room['room_type'] }} — {{ $room['room_number'] }} |
@endforeach
| Check-in | {{ $checkInDate }} |
| Check-out | {{ $checkOutDate }} |
| Nights | {{ $nights }} |
| **Total Paid** | **₱{{ number_format($totalAmount, 2) }}** |
</x-mail::table>

Keep your booking reference and this email's address handy - you can look up or manage your booking anytime using them.

<x-mail::button :url="config('app.frontend_url') . '/find-booking'">
Find My Booking
</x-mail::button>

See you soon!<br>
{{ config('app.name') }}
</x-mail::message>
