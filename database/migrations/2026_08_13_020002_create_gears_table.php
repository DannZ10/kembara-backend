<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gears', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('gear_categories')->onDelete('restrict');
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();
            $table->string('brand', 100)->nullable();
            $table->decimal('price_per_day', 12, 2);
            $table->unsignedInteger('stock_total');
            $table->unsignedInteger('stock_available');
            $table->string('image_url', 255)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index('category_id');
            $table->index('is_available');
            $table->index('price_per_day');
            if (DB::getDriverName() !== 'sqlite') {
                $table->fullText(['name', 'description', 'brand']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gears');
    }
};
