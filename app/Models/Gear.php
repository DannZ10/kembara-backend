<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Gear extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'brand',
        'price_per_day',
        'stock_total',
        'stock_available',
        'image_url',
        'images',
        'weight_kg',
        'is_available',
    ];

    protected $casts = [
        'price_per_day' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'stock_total' => 'integer',
        'stock_available' => 'integer',
        'is_available' => 'boolean',
        'images' => 'array',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GearCategory::class, 'category_id');
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(GearVariant::class);
    }
}
