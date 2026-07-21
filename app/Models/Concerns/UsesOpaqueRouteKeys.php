<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\OpaqueId;
use Illuminate\Database\Eloquent\Model;

trait UsesOpaqueRouteKeys
{
    public function getRouteKey(): mixed
    {
        return OpaqueId::encode($this->getKey(), static::class);
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null && $field !== $this->getKeyName()) {
            return parent::resolveRouteBinding($value, $field);
        }

        $id = OpaqueId::decode($value, static::class);

        return $id ? $this->whereKey($id)->first() : null;
    }

    public function opaqueId(): string
    {
        return OpaqueId::encode($this->getKey(), static::class);
    }
}
