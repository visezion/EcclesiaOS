<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ChildrenYouthRecord extends Model
{
    use UsesOpaqueRouteKeys;
    use SoftDeletes;

    protected $fillable = [
        'church_id',
        'campus_id',
        'member_id',
        'guardian_member_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'age_group',
        'guardian_name',
        'guardian_phone',
        'consent_status',
        'check_in_status',
        'pickup_code',
        'medical_notes',
        'status',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'guardian_member_id');
    }
}
