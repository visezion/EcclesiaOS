<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Volunteer extends Model
{
    use UsesOpaqueRouteKeys;
    protected $fillable = ['church_id', 'campus_id', 'member_id', 'ministry_id', 'role', 'status', 'availability'];

    protected function casts(): array
    {
        return ['availability' => 'array'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
    }
}
