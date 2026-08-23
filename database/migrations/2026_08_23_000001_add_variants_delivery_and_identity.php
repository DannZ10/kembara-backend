<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gear soft-delete: booking_items.gear_id is restrictOnDelete, so a gear
        // with rental history can never be hard-deleted. Soft delete removes it
        // from every catalog/admin listing while keeping the row for history.
        Schema::table('gears', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Per-variant stock (e.g. jacket size L / red). A gear may have zero
        // variants (stock tracked on the gear) or many (stock tracked per variant).
        Schema::create('gear_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gear_id')->constrained('gears')->cascadeOnDelete();
            $table->string('size', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();

            $table->index('gear_id');
        });

        Schema::table('booking_items', function (Blueprint $table) {
            $table->foreignId('gear_variant_id')->nullable()->after('gear_id')
                ->constrained('gear_variants')->nullOnDelete();
            // Denormalized so history still reads correctly if the variant is edited/deleted.
            $table->string('variant_label', 120)->nullable()->after('gear_variant_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->text('delivery_maps_url')->nullable()->after('delivery_address');
            $table->string('identity_type_1', 20)->nullable()->after('identity_verified');
            $table->string('identity_type_2', 20)->nullable()->after('identity_type_1');
        });

        // Runtime-editable key/value config (delivery fee params + basecamp coords).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['delivery_maps_url', 'identity_type_1', 'identity_type_2']);
        });

        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gear_variant_id');
            $table->dropColumn('variant_label');
        });

        Schema::dropIfExists('gear_variants');

        Schema::table('gears', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
