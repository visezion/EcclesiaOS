<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Member extends Model
{
    use HasFactory;
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'campus_id', 'family_id', 'first_name', 'last_name', 'email', 'phone', 'profile_photo_path', 'status', 'joined_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'date'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function volunteers(): HasMany
    {
        return $this->hasMany(Volunteer::class);
    }

    public function prayerRequests(): HasMany
    {
        return $this->hasMany(PrayerRequest::class);
    }

    public function careTasks(): HasMany
    {
        return $this->hasMany(CareTask::class);
    }

    public function counsellingBookings(): HasMany
    {
        return $this->hasMany(CounsellingBooking::class);
    }

    public function assetBookings(): HasMany
    {
        return $this->hasMany(AssetBooking::class);
    }

    public function bookstoreLibraryLoans(): HasMany
    {
        return $this->hasMany(BookstoreLibraryLoan::class);
    }

    public function memberProfile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function historyEntries(): HasMany
    {
        return $this->hasMany(MemberHistoryEntry::class);
    }

    public function userAccount(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
