<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('booking_id')->nullable()->constrained('bookings')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20)->nullable(); // admin | customer | system
            $table->string('action', 50);                 // machine key, e.g. status.changed
            $table->string('description', 255);            // human-readable (Indonesian)
            $table->timestamp('created_at')->useCurrent();

            $table->index('booking_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
