<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GearVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'gear_id',
        'size',
        'color',
        'stock',
    ];

    protected $casts = [
        'stock' => 'integer',
    ];

    protected $appends = ['label'];

    public function gear(): BelongsTo
    {
        return $this->belongsTo(Gear::class);
    }

    /** Human label combining whichever of size/color are set. */
    public function getLabelAttribute(): string
    {
        $parts = array_filter([$this->size, $this->color], fn ($v) => filled($v));

        return $parts ? implode(' · ', $parts) : 'Varian';
    }
}
