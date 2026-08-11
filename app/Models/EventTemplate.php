<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventTemplate extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'campus_id', 'created_by', 'name', 'description', 'event_type', 'venue', 'agenda'];

    protected function casts(): array
    {
        return ['agenda' => 'array'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
