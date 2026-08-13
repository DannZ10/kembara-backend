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

    public function gear(): BelongsTo
    {
        return $this->belongsTo(Gear::class);
    }
}
