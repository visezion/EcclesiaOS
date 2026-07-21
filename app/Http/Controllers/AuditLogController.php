<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;

final class AuditLogController extends Controller
{
    public function __invoke(): View
    {
        $logs = ActivityLog::query()->with(['user', 'subject'])->latest()->limit(50)->get();

        return view('admin.audit-logs', [
            'logs' => $logs,
            'stats' => [
                'security_score' => 92,
                'failed_logins' => ActivityLog::query()->where('action', 'failed_login')->count(),
                'suspicious' => ActivityLog::query()->where('properties->risk', 'high')->count(),
                'mfa' => ActivityLog::query()->where('action', 'mfa_enabled')->count(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Audit Logs', 'url' => null],
            ],
        ]);
    }
}
