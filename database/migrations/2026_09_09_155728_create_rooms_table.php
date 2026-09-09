<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number', 20)->unique();
            $table->string('room_type', 50);
            $table->decimal('price_per_night', 10, 2);
            $table->unsignedInteger('capacity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
