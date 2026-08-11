<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CelebrationDispatch;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationTemplate;
use App\Models\NotificationAutomationRule;
use App\Services\ActivityLogger;
use App\Services\Communications\CelebrationService;
use App\Services\Communications\DomainNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class NotificationAutomationController extends Controller
{
    private const EVENTS = [
        'EventCreated' => ['Event created', 'events'],
        'EventSessionCreated' => ['Event session created', 'events'],
        'EventSessionUpdated' => ['Event or meeting updated', 'events'],
        'EventSessionCancelled' => ['Event session cancelled', 'events'],
        'EventReminderDue' => ['Upcoming event reminder', 'events'],
        'AttendanceSessionOpened' => ['Attendance check-in opened', 'attendance'],
        'AttendanceRecorded' => ['Attendance recorded', 'attendance'],
        'VolunteerAssigned' => ['Volunteer assigned', 'volunteers'],
        'ProgramSectionAssigned' => ['Program responsibility assigned', 'volunteers'],
        'MeetingAgendaAssigned' => ['Meeting agenda responsibility assigned', 'events'],
        'RegistrationConfirmed' => ['Registration confirmed', 'registration'],
        'MemberAccountCreated' => ['Member account created', 'registration'],
        'ApprovalRequested' => ['Approval requested', 'system'],
        'ApprovalDecision' => ['Approval decision', 'system'],
        'PaymentStatusChanged' => ['Giving payment status', 'system'],
        'LibraryLoanNotification' => ['Library request update', 'system'],
        'SupportTicketUpdated' => ['Support ticket update', 'system'],
        'FollowUpRequired' => ['Pastoral follow-up required', 'care'],
    ];

    private const CHANNELS = ['in_app', 'email', 'sms', 'whatsapp', 'push'];

    public function index(Request $request): View
    {
        $this->authorizeAutomation($request);
        $this->ensureDefaults((int) $request->user()->church_id);
        $rules = NotificationAutomationRule::query()
            ->where('church_id', $request->user()->church_id)
            ->with('template')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('communications.automation', [
            'rules' => $rules,
            'events' => self::EVENTS,
            'channels' => self::CHANNELS,
            'templates' => CommunicationTemplate::query()
                ->where('church_id', $request->user()->church_id)
                ->where('status', 'active')
                ->where('approval_state', 'approved')
                ->orderBy('name')
                ->get(),
            'stats' => [
                'enabled' => $rules->where('enabled', true)->count(),
                'disabled' => $rules->where('enabled', false)->count(),
                'healthy' => $rules->where('last_status', 'success')->count(),
                'failed_deliveries' => CommunicationDelivery::query()->where('church_id', $request->user()->church_id)->where('status', 'failed')->count(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Communications', 'url' => route('communications.index')],
                ['label' => 'Notification Automation', 'url' => null],
            ],
        ]);
    }

    public function celebrations(Request $request, CelebrationService $celebrations): View
    {
        $this->authorizeAutomation($request);
        $setting = $celebrations->settings((int) $request->user()->church_id);
        $dispatches = CelebrationDispatch::query()
            ->where('church_id', $request->user()->church_id);

        return view('communications.celebrations', [
            'setting' => $setting,
            'stats' => [
                'birthday' => (clone $dispatches)->where('occasion_type', 'birthday')->whereDate('occasion_date', today())->count(),
                'anniversary' => (clone $dispatches)->where('occasion_type', 'anniversary')->whereDate('occasion_date', today())->count(),
                'sent' => (clone $dispatches)->where('status', 'sent')->whereDate('created_at', today())->count(),
                'failed' => (clone $dispatches)->where('status', 'failed')->whereDate('created_at', today())->count(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Communications', 'url' => route('communications.index')],
                ['label' => 'Celebrations', 'url' => null],
            ],
        ]);
    }

    public function updateCelebrations(Request $request, CelebrationService $celebrations, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeAutomation($request);
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'birthdays_enabled' => ['nullable', 'boolean'],
            'anniversaries_enabled' => ['nullable', 'boolean'],
            'celebrant_channels' => ['required', 'array', 'min:1'],
            'celebrant_channels.*' => [Rule::in(self::CHANNELS)],
            'send_time' => ['required', 'date_format:H:i'],
            'birthday_subject' => ['required', 'string', 'max:180'],
            'birthday_message' => ['required', 'string', 'max:5000'],
            'birthday_group_message' => ['required', 'string', 'max:5000'],
            'anniversary_subject' => ['required', 'string', 'max:180'],
            'anniversary_message' => ['required', 'string', 'max:5000'],
            'anniversary_group_message' => ['required', 'string', 'max:5000'],
            'design.frame' => ['required', Rule::in(['sunrise', 'elegant', 'botanical', 'royal'])],
            'design.accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.background' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.footer' => ['required', 'string', 'max:120'],
        ]);
        $setting = $celebrations->settings((int) $request->user()->church_id);
        $setting->update([
            ...$validated,
            'enabled' => $request->boolean('enabled'),
            'birthdays_enabled' => $request->boolean('birthdays_enabled'),
            'anniversaries_enabled' => $request->boolean('anniversaries_enabled'),
            'design' => $validated['design'],
        ]);
        $logger->log('Communications', 'celebration_settings_updated', 'Birthday and anniversary celebration automation was updated.', $setting, ['resource' => 'Celebration Settings', 'status' => 'success'], $request);

        return back()->with('status', 'Celebration automation saved.');
    }

    public function update(Request $request, NotificationAutomationRule $automationRule, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeRule($request, $automationRule);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'enabled' => ['nullable', 'boolean'],
            'category' => ['required', Rule::in(['events', 'attendance', 'care', 'volunteers', 'registration', 'system'])],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(self::CHANNELS)],
            'audience' => ['required', Rule::in(['event_recipients', 'all_users', 'all_members', 'administrators'])],
            'communication_template_id' => ['nullable', Rule::exists('communication_templates', 'id')->where(fn ($query) => $query->where('church_id', $request->user()->church_id))],
            'reminder_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'critical' => ['nullable', 'boolean'],
        ]);
        $automationRule->update([
            ...$validated,
            'enabled' => $request->boolean('enabled'),
            'channels' => array_values(array_unique($validated['channels'])),
            'critical' => $request->boolean('critical'),
        ]);
        $logger->log('Communications', 'automation_rule_updated', $automationRule->name.' automation was updated.', $automationRule, ['resource' => 'Notification Automation', 'status' => 'success'], $request);

        return back()->with('status', 'Notification automation saved.');
    }

    public function test(Request $request, NotificationAutomationRule $automationRule, DomainNotificationService $notifications, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeRule($request, $automationRule);
        $user = $request->user();
        $deliveries = $notifications->user(
            $user,
            $automationRule->event_type,
            $automationRule->category,
            'Test: '.$automationRule->name,
            'This is a notification automation test sent from EcclesiaOS.',
            $automationRule->channels,
            ['url' => route('communications.automation'), '_automation_test' => true],
            $automationRule->critical,
        );
        $successful = $deliveries->where('status', 'delivered')->count();
        $automationRule->update([
            'last_run_at' => now(),
            'last_status' => $successful > 0 ? 'success' : 'failed',
            'last_recipient_count' => 1,
            'last_error' => $successful > 0 ? null : $deliveries->pluck('error')->filter()->join('; '),
        ]);
        $logger->log('Communications', 'automation_rule_tested', $automationRule->name.' automation was tested.', $automationRule, ['resource' => 'Notification Automation', 'status' => $successful > 0 ? 'success' : 'failed'], $request);

        return back()->with($successful > 0 ? 'status' : 'error', $successful > 0 ? 'Automation test delivered.' : 'Automation test did not deliver. Review the channel configuration and recipient preferences.');
    }

    public function retryFailed(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeAutomation($request);
        $count = CommunicationDelivery::query()
            ->where('church_id', $request->user()->church_id)
            ->where('status', 'failed')
            ->update([
                'status' => 'queued',
                'retry_status' => 'queued',
                'available_at' => now(),
                'error' => null,
            ]);
        $logger->log('Communications', 'failed_deliveries_requeued', $count.' failed deliveries were requeued.', null, ['resource' => 'Notification Automation', 'status' => 'success'], $request);

        return back()->with('status', number_format($count).' failed deliveries queued for retry.');
    }

    private function ensureDefaults(int $churchId): void
    {
        foreach (self::EVENTS as $eventType => [$name, $category]) {
            NotificationAutomationRule::query()->firstOrCreate(
                ['church_id' => $churchId, 'event_type' => $eventType],
                [
                    'name' => $name,
                    'category' => $category,
                    'enabled' => true,
                    'channels' => ['in_app'],
                    'audience' => 'event_recipients',
                    'reminder_minutes' => $eventType === 'EventReminderDue' ? 1440 : null,
                    'critical' => in_array($eventType, ['ApprovalRequested', 'PaymentStatusChanged'], true),
                ],
            );
        }
    }

    private function authorizeAutomation(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage communications'), 403);
    }

    private function authorizeRule(Request $request, NotificationAutomationRule $rule): void
    {
        $this->authorizeAutomation($request);
        abort_unless($request->user()?->canAccessChurch($rule->church_id), 403);
    }
}
