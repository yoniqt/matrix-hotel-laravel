<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per photo (not a JSON array column) so adding/removing a
        // single photo is a plain insert/delete instead of read-modify-write
        // on a shared array.
        Schema::create('room_type_photos', function (Blueprint $table) {
            $table->id();
            $table->string('room_type', 50);
            $table->string('path');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_type_photos');
    }
};
