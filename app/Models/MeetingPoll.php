<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MeetingPoll extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'campus_id', 'event_session_id', 'question', 'is_open', 'show_results', 'created_by'];

    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'show_results' => 'boolean'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(MeetingPollOption::class)->orderBy('position');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(MeetingPollVote::class);
    }
}
