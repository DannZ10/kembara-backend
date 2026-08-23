<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\DeliveryType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'booking_code',
        'start_date',
        'end_date',
        'duration_days',
        'delivery_type',
        'delivery_address',
        'delivery_maps_url',
        'delivery_distance_km',
        'delivery_fee',
        'subtotal',
        'total_price',
        'status',
        'identity_verified',
        'identity_type_1',
        'identity_type_2',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'duration_days' => 'integer',
            'delivery_type' => DeliveryType::class,
            'delivery_distance_km' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total_price' => 'decimal:2',
            'status' => BookingStatus::class,
            'identity_verified' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
