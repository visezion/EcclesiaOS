<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MemberImport extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'reference', 'church_id', 'created_by', 'profile_id', 'connection_id', 'name',
        'source_type', 'status', 'disk', 'path', 'original_filename', 'source_table',
        'source_options', 'mapping', 'options', 'total_rows', 'processed_rows',
        'created_rows', 'updated_rows', 'skipped_rows', 'failed_rows', 'summary',
        'error', 'started_at', 'completed_at', 'rolled_back_at', 'rolled_back_by',
    ];

    protected function casts(): array
    {
        return [
            'source_options' => 'array',
            'mapping' => 'array',
            'options' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(MemberImportProfile::class, 'profile_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MemberImportConnection::class, 'connection_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(MemberImportRow::class);
    }
}
