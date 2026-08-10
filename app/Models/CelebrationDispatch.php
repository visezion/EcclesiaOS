<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CelebrationDispatch extends Model
{
    protected $fillable = [
        'church_id', 'celebration_setting_id', 'member_id', 'family_id',
        'occasion_type', 'occasion_date', 'years', 'image_path', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return ['occasion_date' => 'date', 'metadata' => 'array'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
