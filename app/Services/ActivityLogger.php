<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ActivityLogger
{
    public function log(string $module, string $action, string $description, ?Model $subject = null, array $properties = [], ?Request $request = null): void
    {
        $user = Auth::user();

        ActivityLog::query()->create([
            'church_id' => $user?->church_id,
            'campus_id' => $user?->campus_id,
            'user_id' => $user?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'properties' => $properties,
        ]);
    }
}
