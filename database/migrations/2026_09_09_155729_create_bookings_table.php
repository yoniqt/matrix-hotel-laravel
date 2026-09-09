<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->text('special_requests')->nullable();
            $table->string('status', 20)->default('confirmed');
            $table->timestamp('created_at')->useCurrent();
            $table->unsignedInteger('guests_count')->default(1);
            $table->string('booking_reference', 20)->default('');
            $table->string('payment_status', 20)->default('pending');

            $table->index('booking_reference', 'idx_booking_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
