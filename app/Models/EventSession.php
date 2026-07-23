<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class EventSession extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'church_id',
        'campus_id',
        'event_id',
        'recurrence_rule_id',
        'title',
        'session_date',
        'starts_at',
        'ends_at',
        'timezone',
        'meeting_type',
        'venue',
        'address',
        'capacity',
        'status',
        'meeting_links',
        'settings',
    ];

    protected function casts(): array
    {
        return ['session_date' => 'date', 'meeting_links' => 'array', 'settings' => 'array'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function recurrenceRule(): BelongsTo
    {
        return $this->belongsTo(EventRecurrenceRule::class, 'recurrence_rule_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function attendanceSession(): HasOne
    {
        return $this->hasOne(AttendanceSession::class);
    }
}
