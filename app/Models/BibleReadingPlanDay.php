<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BibleReadingPlanDay extends Model
{
    protected $fillable = ['bible_reading_plan_id', 'day_number', 'title', 'passages', 'reflection'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BibleReadingPlan::class, 'bible_reading_plan_id');
    }
}
