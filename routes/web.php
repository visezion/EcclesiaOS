<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CampusManagementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleDirectoryController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserDirectoryController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');
    Route::get('settings', AccessControlController::class)->name('settings.index');
    Route::get('administration/users', UserDirectoryController::class)->name('users.index');
    Route::get('administration/users/export', [UserDirectoryController::class, 'export'])->name('users.export');
    Route::get('administration/roles', RoleDirectoryController::class)->name('roles.index');
    Route::get('administration/roles/report', [RolePermissionController::class, 'report'])->name('roles.report');
    Route::get('administration/campuses', CampusManagementController::class)->name('campuses.index');
    Route::post('administration/campuses', [CampusManagementController::class, 'store'])->name('campuses.store');
    Route::post('administration/campuses/import', [CampusManagementController::class, 'import'])->name('campuses.import');
    Route::get('administration/audit-logs', AuditLogController::class)->name('audit-logs.index');
    Route::post('settings/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::post('settings/users/bulk', [UserManagementController::class, 'bulk'])->name('users.bulk');
    Route::put('settings/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::post('settings/roles', [RolePermissionController::class, 'store'])->name('roles.store');
    Route::post('settings/roles/{role}/clone', [RolePermissionController::class, 'clone'])->name('roles.clone');
    Route::put('settings/roles/{role}/reset', [RolePermissionController::class, 'reset'])->name('roles.reset');
    Route::put('settings/roles/{role}', [RolePermissionController::class, 'update'])->name('roles.update');

    foreach (collect(config('navigation'))->flatMap(fn (array $item): array => $item['children'] ?? [$item]) as $item) {
        if (in_array(($item['route'] ?? null), ['dashboard', 'settings.index', 'users.index', 'roles.index', 'campuses.index', 'audit-logs.index'], true)) {
            continue;
        }

        Route::get(str_replace('.index', '', (string) $item['route']), ModuleController::class)->name($item['route']);
    }

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('profile/impersonate', [ProfileController::class, 'impersonate'])->name('profile.impersonate');
    Route::get('account/settings', ModuleController::class)->defaults('module', 'account')->name('account.settings');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('admin-only', fn () => 'ok')->middleware('role:Super Administrator')->name('admin.only');
});
