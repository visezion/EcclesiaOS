<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');

    foreach (config('navigation') as $item) {
        if (($item['route'] ?? null) === 'dashboard') {
            continue;
        }

        Route::get(str_replace('.index', '', (string) $item['route']), ModuleController::class)->name($item['route']);
    }

    Route::get('profile', ModuleController::class)->defaults('module', 'profile')->name('profile.edit');
    Route::get('account/settings', ModuleController::class)->defaults('module', 'account')->name('account.settings');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('admin-only', fn () => 'ok')->middleware('role:Super Administrator')->name('admin.only');
});
