<?php

declare(strict_types=1);

use App\Support\OpaqueId;

if (! function_exists('opaque_id')) {
    function opaque_id(int|string|null $id, string $scope): string
    {
        return OpaqueId::encode($id, $scope);
    }
}
