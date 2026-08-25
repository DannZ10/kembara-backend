<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Serah-terima gear: kapan diambil/dikembalikan customer.
            $table->timestamp('picked_up_at')->nullable()->after('status');
            $table->timestamp('returned_at')->nullable()->after('picked_up_at');
            // Apakah 2 kartu identitas jaminan sudah dikembalikan ke customer.
            $table->boolean('identity_returned')->default(false)->after('identity_type_2');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['picked_up_at', 'returned_at', 'identity_returned']);
        });
    }
};
