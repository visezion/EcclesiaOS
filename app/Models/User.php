<?php

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use UsesOpaqueRouteKeys;
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'title',
        'employee_id',
        'date_joined',
        'date_of_birth',
        'gender',
        'address',
        'timezone',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'recovery_email',
        'mfa_enabled',
        'avatar_url',
        'account_settings',
        'status',
        'church_id',
        'campus_id',
        'last_login_at',
        'password_changed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_joined' => 'date',
            'date_of_birth' => 'date',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'mfa_enabled' => 'boolean',
            'account_settings' => 'array',
            'password' => 'hashed',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasAnyRole(['Super Administrator'])) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }

    public function isSuperAdministrator(): bool
    {
        return $this->hasAnyRole(['Super Administrator']);
    }

    public function canAccessChurch(?int $churchId): bool
    {
        return $this->isSuperAdministrator() || $churchId === null || $this->church_id === $churchId;
    }

    public function canAccessCampus(?int $campusId): bool
    {
        return $this->isSuperAdministrator() || $campusId === null || $this->campus_id === null || $this->campus_id === $campusId;
    }

    public function getAvatarSrcAttribute(): ?string
    {
        if (! filled($this->avatar_url)) {
            return null;
        }

        if (Str::startsWith($this->avatar_url, ['http://', 'https://', 'data:'])) {
            return $this->avatar_url;
        }

        if (Str::startsWith($this->avatar_url, ['/storage/', 'storage/'])) {
            return asset(ltrim($this->avatar_url, '/'));
        }

        return asset('storage/'.ltrim($this->avatar_url, '/'));
    }
}
