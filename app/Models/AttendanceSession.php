<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AttendanceSession extends Model
{
    use UsesOpaqueRouteKeys;
    use SoftDeletes;

    protected $fillable = [
        'church_id',
        'campus_id',
        'event_session_id',
        'title',
        'opens_at',
        'closes_at',
        'methods',
        'verification_policy',
        'require_authenticated',
        'allow_guests',
        'geo_latitude',
        'geo_longitude',
        'geo_radius_meters',
        'expected_attendance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'methods' => 'array',
            'require_authenticated' => 'boolean',
            'allow_guests' => 'boolean',
            'geo_latitude' => 'decimal:7',
            'geo_longitude' => 'decimal:7',
        ];
    }

    public function eventSession(): BelongsTo
    {
        return $this->belongsTo(EventSession::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(AttendanceVerification::class);
    }
}
