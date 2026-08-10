<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CelebrationSetting extends Model
{
    protected $fillable = [
        'church_id', 'enabled', 'birthdays_enabled', 'anniversaries_enabled',
        'celebrant_channels', 'send_time', 'birthday_subject', 'birthday_message',
        'birthday_group_message', 'anniversary_subject', 'anniversary_message',
        'anniversary_group_message', 'design',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'birthdays_enabled' => 'boolean',
            'anniversaries_enabled' => 'boolean',
            'celebrant_channels' => 'array',
            'design' => 'array',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
