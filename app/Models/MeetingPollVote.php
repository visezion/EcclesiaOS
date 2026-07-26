<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MeetingPollVote extends Model
{
    protected $fillable = ['meeting_poll_id', 'meeting_poll_option_id', 'user_id', 'guest_identity'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(MeetingPoll::class, 'meeting_poll_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(MeetingPollOption::class, 'meeting_poll_option_id');
    }
}
