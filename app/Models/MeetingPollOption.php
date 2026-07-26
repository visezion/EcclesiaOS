<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MeetingPollOption extends Model
{
    protected $fillable = ['meeting_poll_id', 'label', 'position', 'votes_count'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(MeetingPoll::class, 'meeting_poll_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(MeetingPollVote::class);
    }
}
