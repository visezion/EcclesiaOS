<?php

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CampusManagementController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventFlowController;
use App\Http\Controllers\FamilyManagementController;
use App\Http\Controllers\MemberManagementController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PastoralCareController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleDirectoryController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\UserDirectoryController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/meeting-attendance/{provider}', [EventFlowController::class, 'onlineAttendanceWebhook'])
    ->name('meeting-attendance.webhook')
    ->withoutMiddleware([VerifyCsrfToken::class]);

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
    Route::get('programs', [EventFlowController::class, 'programs'])->name('programs.index');
    Route::post('programs', [EventFlowController::class, 'storeProgram'])->name('programs.store');
    Route::get('programs/{program}/events', [EventFlowController::class, 'events'])->name('programs.events');
    Route::post('programs/{program}/events', [EventFlowController::class, 'storeEvent'])->name('programs.events.store');
    Route::get('events', [EventFlowController::class, 'events'])->name('events.index');
    Route::get('programs/{program}/events/{event}/sessions', [EventFlowController::class, 'sessions'])->name('event-sessions.index');
    Route::post('programs/{program}/events/{event}/sessions', [EventFlowController::class, 'storeSession'])->name('event-sessions.store');
    Route::get('calendar', [EventFlowController::class, 'calendar'])->name('calendar.index');
    Route::get('meetings', [EventFlowController::class, 'meetings'])->name('meetings.index');
    Route::get('event-sessions/{eventSession}/meeting', [EventFlowController::class, 'meeting'])->name('event-sessions.meeting');
    Route::put('event-sessions/{eventSession}/meeting', [EventFlowController::class, 'updateMeeting'])->name('event-sessions.meeting.update');
    Route::get('meetings/rooms/{eventSession}/{provider}', [EventFlowController::class, 'room'])->name('meetings.rooms.show');
    Route::post('meetings/rooms/{eventSession}/{provider}/attendance', [EventFlowController::class, 'markRoomAttendance'])->name('meetings.rooms.attendance.store');
    Route::post('meetings/rooms/{eventSession}/{provider}/checkout', [EventFlowController::class, 'markRoomCheckout'])->name('meetings.rooms.checkout.store');
    Route::get('event-sessions/{eventSession}/attendance', [EventFlowController::class, 'attendance'])->name('event-sessions.attendance');
    Route::put('event-sessions/{eventSession}/attendance', [EventFlowController::class, 'updateAttendance'])->name('event-sessions.attendance.update');
    Route::get('attendance', [EventFlowController::class, 'attendanceIndex'])->name('attendance.index');
    Route::get('attendance/{attendanceSession}/methods', [EventFlowController::class, 'methods'])->name('attendance.methods');
    Route::post('attendance/{attendanceSession}/check-in', [EventFlowController::class, 'checkIn'])->name('attendance.check-in');
    Route::get('attendance/{attendanceSession}/records/{member}', [EventFlowController::class, 'record'])->name('attendance.records.show');
    Route::get('administration/meeting-integrations', [EventFlowController::class, 'integrations'])->name('meeting-integrations.index');
    Route::put('administration/meeting-integrations', [EventFlowController::class, 'updateIntegrations'])->name('meeting-integrations.update');
    Route::post('administration/meeting-integrations/{provider}/test', [EventFlowController::class, 'testIntegration'])->name('meeting-integrations.test');
    Route::get('members/follow-up', [PastoralCareController::class, 'index'])->name('members.follow-up');
    Route::post('members/follow-up', [PastoralCareController::class, 'store'])->name('care-tasks.store');
    Route::post('members/follow-up/bulk', [PastoralCareController::class, 'bulk'])->name('care-tasks.bulk');
    Route::get('members/follow-up/export', [PastoralCareController::class, 'export'])->name('care-tasks.export');
    Route::put('members/follow-up/{task}', [PastoralCareController::class, 'update'])->name('care-tasks.update');
    Route::get('members', [MemberManagementController::class, 'index'])->name('members.index');
    Route::get('members/create', [MemberManagementController::class, 'create'])->name('members.create');
    Route::post('members', [MemberManagementController::class, 'store'])->name('members.store');
    Route::post('members/import', [MemberManagementController::class, 'import'])->name('members.import');
    Route::get('members/export', [MemberManagementController::class, 'export'])->name('members.export');
    Route::get('members/bulk', fn () => redirect()->route('members.index')->with('error', 'Use the Bulk Actions menu to apply changes to selected members.'))->name('members.bulk.fallback');
    Route::post('members/bulk', [MemberManagementController::class, 'bulk'])->name('members.bulk');
    Route::get('members/{member}', [MemberManagementController::class, 'show'])->name('members.show');
    Route::post('members/{member}/check-in', [MemberManagementController::class, 'checkIn'])->name('members.check-in');
    Route::post('members/{member}/assign-ministry', [MemberManagementController::class, 'assignMinistry'])->name('members.assign-ministry');
    Route::put('members/{member}', [MemberManagementController::class, 'update'])->name('members.update');
    Route::delete('members/{member}', [MemberManagementController::class, 'destroy'])->name('members.destroy');
    Route::get('families', [FamilyManagementController::class, 'index'])->name('families.index');
    Route::post('families', [FamilyManagementController::class, 'store'])->name('families.store');
    Route::get('families/export', [FamilyManagementController::class, 'export'])->name('families.export');
    Route::put('families/{family}', [FamilyManagementController::class, 'update'])->name('families.update');
    Route::delete('families/{family}', [FamilyManagementController::class, 'destroy'])->name('families.destroy');
    Route::get('settings', SystemSettingsController::class)->name('settings.index');
    Route::put('settings/system', [SystemSettingsController::class, 'update'])->name('settings.system.update');
    Route::put('settings/system/reset', [SystemSettingsController::class, 'reset'])->name('settings.system.reset');
    Route::post('settings/system/test-connection', [SystemSettingsController::class, 'testConnection'])->name('settings.system.test-connection');
    Route::post('settings/branding/sidebar-background', [BrandingController::class, 'updateSidebarBackground'])->name('settings.branding.sidebar-background');
    Route::delete('settings/branding/sidebar-background', [BrandingController::class, 'resetSidebarBackground'])->name('settings.branding.sidebar-background.reset');
    Route::get('communications', [CommunicationController::class, 'overview'])->name('communications.index');
    Route::get('communications/notifications', [CommunicationController::class, 'notifications'])->name('communications.notifications');
    Route::post('communications/notifications/read-all', [CommunicationController::class, 'markAllNotificationsRead'])->name('communications.notifications.read-all');
    Route::post('communications/notifications/archive-old', [CommunicationController::class, 'archiveOldNotifications'])->name('communications.notifications.archive-old');
    Route::post('communications/notifications/{delivery}/read', [CommunicationController::class, 'markNotificationRead'])->name('communications.notifications.read');
    Route::post('communications/notifications/{delivery}/archive', [CommunicationController::class, 'archiveNotification'])->name('communications.notifications.archive');
    Route::get('communications/templates', [CommunicationController::class, 'templates'])->name('communications.templates');
    Route::get('communications/templates/export', [CommunicationController::class, 'exportTemplates'])->name('communications.templates.export');
    Route::post('communications/templates', [CommunicationController::class, 'storeTemplate'])->name('communications.templates.store');
    Route::put('communications/templates/{template}', [CommunicationController::class, 'updateTemplate'])->name('communications.templates.update');
    Route::delete('communications/templates/{template}', [CommunicationController::class, 'deleteTemplate'])->name('communications.templates.destroy');
    Route::post('communications/templates/{template}/clone', [CommunicationController::class, 'cloneTemplate'])->name('communications.templates.clone');
    Route::post('communications/templates/{template}/test-send', [CommunicationController::class, 'testSendTemplate'])->name('communications.templates.test-send');
    Route::get('communications/scheduled', [CommunicationController::class, 'scheduled'])->name('communications.scheduled');
    Route::get('communications/bulk', [CommunicationController::class, 'bulk'])->name('communications.bulk');
    Route::post('communications/campaigns', [CommunicationController::class, 'storeCampaign'])->name('communications.campaigns.store');
    Route::post('communications/campaigns/{campaign}/send', [CommunicationController::class, 'sendCampaignNow'])->name('communications.campaigns.send');
    Route::delete('communications/campaigns/{campaign}', [CommunicationController::class, 'deleteCampaign'])->name('communications.campaigns.destroy');
    Route::get('communications/delivery-logs', [CommunicationController::class, 'deliveryLogs'])->name('communications.delivery-logs');
    Route::get('communications/delivery-logs/export', [CommunicationController::class, 'exportDeliveries'])->name('communications.delivery-logs.export');
    Route::post('communications/delivery-logs/{delivery}/retry', [CommunicationController::class, 'retryDelivery'])->name('communications.delivery-logs.retry');
    Route::get('communications/preferences', [CommunicationController::class, 'preferences'])->name('communications.preferences');
    Route::get('communications/preferences/export', [CommunicationController::class, 'exportPreferences'])->name('communications.preferences.export');
    Route::post('communications/preferences/defaults', [CommunicationController::class, 'applyDefaultPreferences'])->name('communications.preferences.defaults');
    Route::post('communications/preferences/reminders', [CommunicationController::class, 'sendPreferenceReminder'])->name('communications.preferences.reminders');
    Route::post('communications/preferences/import', [CommunicationController::class, 'importPreferences'])->name('communications.preferences.import');
    Route::put('communications/preferences/{preference}', [CommunicationController::class, 'updatePreference'])->name('communications.preferences.update');
    Route::get('communications/integrations', [CommunicationController::class, 'integrations'])->name('communications.integrations');
    Route::put('communications/integrations', [CommunicationController::class, 'updateIntegrations'])->name('communications.integrations.update');
    Route::post('communications/integrations/{channel}/test', [CommunicationController::class, 'testIntegration'])->name('communications.integrations.test');
    Route::get('administration/users', UserDirectoryController::class)->name('users.index');
    Route::get('administration/users/export', [UserDirectoryController::class, 'export'])->name('users.export');
    Route::get('administration/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
    Route::get('administration/roles', RoleDirectoryController::class)->name('roles.index');
    Route::get('administration/roles/report', [RolePermissionController::class, 'report'])->name('roles.report');
    Route::get('administration/campuses', CampusManagementController::class)->name('campuses.index');
    Route::post('administration/campuses', [CampusManagementController::class, 'store'])->name('campuses.store');
    Route::post('administration/campuses/import', [CampusManagementController::class, 'import'])->name('campuses.import');
    Route::get('administration/audit-logs', AuditLogController::class)->name('audit-logs.index');
    Route::get('administration/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
    Route::post('settings/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::post('settings/users/bulk', [UserManagementController::class, 'bulk'])->name('users.bulk');
    Route::post('settings/users/impersonation/stop', [UserManagementController::class, 'stopImpersonating'])->name('users.impersonation.stop');
    Route::put('settings/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::put('settings/users/{user}/password', [UserManagementController::class, 'resetPassword'])->name('users.password');
    Route::post('settings/users/{user}/message', [UserManagementController::class, 'message'])->name('users.message');
    Route::post('settings/users/{user}/impersonate', [UserManagementController::class, 'impersonate'])->name('users.impersonate');
    Route::post('settings/roles', [RolePermissionController::class, 'store'])->name('roles.store');
    Route::post('settings/roles/{role}/clone', [RolePermissionController::class, 'clone'])->name('roles.clone');
    Route::put('settings/roles/{role}/reset', [RolePermissionController::class, 'reset'])->name('roles.reset');
    Route::put('settings/roles/{role}', [RolePermissionController::class, 'update'])->name('roles.update');

    foreach (collect(config('navigation'))->flatMap(fn (array $item): array => $item['children'] ?? [$item]) as $item) {
        if (in_array(($item['route'] ?? null), ['dashboard', 'programs.index', 'events.index', 'calendar.index', 'meetings.index', 'attendance.index', 'members.index', 'families.index', 'settings.index', 'users.index', 'roles.index', 'campuses.index', 'audit-logs.index', 'meeting-integrations.index', 'communications.index', 'communications.notifications', 'communications.templates', 'communications.scheduled', 'communications.bulk', 'communications.delivery-logs', 'communications.preferences', 'communications.integrations'], true)) {
            continue;
        }

        Route::get(str_replace('.index', '', (string) $item['route']), ModuleController::class)->name($item['route']);
    }

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('profile/impersonate', [ProfileController::class, 'impersonate'])->name('profile.impersonate');
    Route::get('account/settings', [AccountSettingsController::class, 'edit'])->name('account.settings');
    Route::put('account/settings', [AccountSettingsController::class, 'update'])->name('account.settings.update');
    Route::post('account/settings/test-notification', [AccountSettingsController::class, 'testNotification'])->name('account.settings.test-notification');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('admin-only', fn () => 'ok')->middleware('role:Super Administrator')->name('admin.only');
});
