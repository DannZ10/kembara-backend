<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'gear_id',
        'gear_variant_id',
        'variant_label',
        'quantity',
        'price_per_day',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_per_day' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    // withTrashed so a soft-deleted gear still resolves in booking history.
    public function gear(): BelongsTo
    {
        return $this->belongsTo(Gear::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(GearVariant::class, 'gear_variant_id');
    }
}
