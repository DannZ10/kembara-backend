<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gears', function (Blueprint $table) {
            // Extra gallery images (JSON array of URLs); image_url stays the primary/cover.
            $table->json('images')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('gears', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
