<?php

use App\Enums\BookingStatus;
use App\Enums\DeliveryType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('booking_code')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('duration_days');
            $table->string('delivery_type')->default(DeliveryType::PICKUP->value);
            $table->text('delivery_address')->nullable();
            $table->decimal('delivery_distance_km', 8, 2)->nullable();
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->string('status')->default(BookingStatus::PENDING->value);
            $table->boolean('identity_verified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
