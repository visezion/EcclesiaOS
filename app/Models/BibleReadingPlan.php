<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BibleReadingPlan extends Model
{
    protected $fillable = ['church_id', 'name', 'description', 'image_path', 'category', 'duration_days', 'is_recommended'];

    protected function casts(): array
    {
        return ['is_recommended' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['current_day', 'current_streak', 'started_at', 'completed_at', 'last_read_at'])->withTimestamps();
    }

    public function days(): HasMany
    {
        return $this->hasMany(BibleReadingPlanDay::class)->orderBy('day_number');
    }
}
