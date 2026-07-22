<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AttendanceVerification extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'attendance_session_id',
        'attendance_record_id',
        'member_id',
        'method',
        'provider',
        'status',
        'confidence',
        'verified_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'metadata' => 'array'];
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
