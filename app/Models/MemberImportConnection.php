<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberImportConnection extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'church_id', 'created_by', 'name', 'driver', 'host', 'port', 'database_name',
        'username', 'password_encrypted', 'options', 'is_active', 'last_tested_at',
        'last_test_status', 'last_error',
    ];

    protected $hidden = ['password_encrypted'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
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
}
