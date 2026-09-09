<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

// Sweeps for pending bookings past their 30-minute payment window, so a
// room's hold is released even if nobody has the payment modal open
// polling that specific booking (BookingController::expireStalePending
// handles the on-demand case for whoever IS polling).
class ExpireStalePendingBookings extends Command
{
    protected $signature = 'bookings:expire-stale-pending';

    protected $description = 'Cancel pending bookings whose 30-minute payment window has passed';

    public function handle(): void
    {
        $count = Booking::where('payment_status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->update(['status' => 'cancelled', 'payment_status' => 'expired']);

        if ($count > 0) {
            $this->info("Expired {$count} stale pending booking row(s).");
        }
    }
}
