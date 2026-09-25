<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_id',
        'units',
        'start_time',
        'end_time',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'resource_id' => 'integer',
        'units' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'expires_at' => 'datetime',
        'status' => ReservationStatus::class,
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ReservationHistory::class);
    }
}
