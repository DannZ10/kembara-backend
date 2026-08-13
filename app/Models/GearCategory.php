<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class GearCategory extends Model
{
    use HasFactory, HasSlug;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_url',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function gears(): HasMany
    {
        return $this->hasMany(Gear::class, 'category_id');
    }
}
