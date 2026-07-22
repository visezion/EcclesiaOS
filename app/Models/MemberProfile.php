<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MemberProfile extends Model
{
    use UsesOpaqueRouteKeys;
    use SoftDeletes;

    protected $fillable = [
        'member_id',
        'preferred_name',
        'date_of_birth',
        'gender',
        'marital_status',
        'anniversary_date',
        'occupation',
        'employer',
        'place_of_birth',
        'nationality',
        'address_line',
        'city',
        'state',
        'postal_code',
        'country',
        'alternate_email',
        'home_phone',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'emergency_contact_alt_phone',
        'care_level',
        'care_notes',
        'communication_preferences',
        'spiritual_journey',
        'skills',
        'documents',
        'volunteer_hours',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'anniversary_date' => 'date',
            'communication_preferences' => 'array',
            'spiritual_journey' => 'array',
            'skills' => 'array',
            'documents' => 'array',
            'volunteer_hours' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
