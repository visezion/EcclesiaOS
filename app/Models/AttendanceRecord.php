<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AttendanceRecord extends Model
{
    use UsesOpaqueRouteKeys;
    protected $fillable = ['church_id', 'campus_id', 'event_id', 'attendance_session_id', 'member_id', 'service_date', 'status', 'final_method', 'verification_summary', 'checked_in_at', 'metadata'];

    protected function casts(): array
    {
        return ['service_date' => 'date', 'checked_in_at' => 'datetime', 'verification_summary' => 'array', 'metadata' => 'array'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(AttendanceVerification::class);
    }
}
