<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false; // only created_at is tracked

    protected $fillable = [
        'booking_id',
        'actor_id',
        'actor_role',
        'action',
        'description',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Record an audit entry, auto-attributing the current authenticated user
     * (falls back to "system" for unauthenticated actions like the webhook).
     */
    public static function record(string $bookingId, string $action, string $description): void
    {
        $user = auth()->user();
        $role = $user
            ? ($user->role instanceof UserRole ? $user->role->value : $user->role)
            : 'system';

        static::create([
            'booking_id' => $bookingId,
            'actor_id' => $user?->id,
            'actor_role' => $role,
            'action' => $action,
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
