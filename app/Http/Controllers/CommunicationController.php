<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Church;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationProviderSetting;
use App\Models\CommunicationRecipient;
use App\Models\CommunicationTemplate;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\ActivityLogger;
use App\Support\OpaqueId;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

final class CommunicationController extends Controller
{
    private const CHANNELS = ['in_app', 'email', 'sms', 'whatsapp', 'push'];

    private const CATEGORIES = ['events', 'attendance', 'care', 'volunteers', 'registration', 'system'];

    private const TRIGGERS = [
        'EventSessionCreated',
        'EventSessionUpdated',
        'EventSessionCancelled',
        'AttendanceSessionOpened',
        'AttendanceRecorded',
        'VolunteerAssigned',
        'RegistrationConfirmed',
        'FollowUpRequired',
    ];

    public function overview(Request $request): View
    {
        $this->authorizeCommunications($request);

        return view('communications.overview', $this->shared($request) + [
            'stats' => $this->stats($request),
            'triggers' => $this->triggerQueue($request),
            'queuedListeners' => $this->queuedListeners($request),
            'recentDeliveries' => $this->deliveries($request)->latest()->limit(6)->get(),
            'channelMix' => $this->channelMix($request),
            'trend' => $this->deliveryTrend($request),
            'trendSeries' => $this->deliveryTrendSeries($request),
            'providerHealth' => $this->providerHealth($request),
            'operationalInsights' => $this->operationalInsights($request),
            'historySummary' => $this->historySummary($request),
            'scheduled' => $this->campaigns($request)
                ->whereBetween('scheduled_at', [now(), now()->addDays(7)])
                ->whereIn('status', ['draft', 'scheduled', 'queued'])
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get(),
            'breadcrumbs' => $this->breadcrumbs('Overview'),
        ]);
    }

    public function notifications(Request $request): View
    {
        $this->authorizeCommunications($request);

        $notifications = $this->deliveries($request)
            ->with(['campaign.creator', 'template', 'member'])
            ->when(filled($request->query('channel')), fn (Builder $query) => $query->where('channel', $request->query('channel')))
            ->when(filled($request->query('status')), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when(filled($request->query('event_type')), fn (Builder $query) => $query->where('event_type', $request->query('event_type')))
            ->when(filled($request->query('priority')), function (Builder $query) use ($request): void {
                match ($request->query('priority')) {
                    'high' => $query->where('status', 'failed'),
                    'medium' => $query->where('status', 'queued'),
                    'low' => $query->where('status', 'delivered'),
                    default => null,
                };
            })
            ->when(filled($request->query('recipient_type')), function (Builder $query) use ($request): void {
                match ($request->query('recipient_type')) {
                    'member' => $query->whereNotNull('member_id'),
                    'system' => $query->whereNull('member_id'),
                    default => null,
                };
            })
            ->when(filled($request->query('date_range')), function (Builder $query) use ($request): void {
                match ($request->query('date_range')) {
                    'today' => $query->whereDate('created_at', today()),
                    '7_days' => $query->where('created_at', '>=', now()->subDays(7)),
                    '30_days' => $query->where('created_at', '>=', now()->subDays(30)),
                    default => null,
                };
            })
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = strtolower((string) $request->query('q'));
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(recipient_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(event_type) LIKE ?', ['%'.$search.'%']));
            })
            ->when($request->query('sort') === 'oldest', fn (Builder $query) => $query->oldest(), fn (Builder $query) => $query->latest())
            ->paginate(10)
            ->withQueryString();
        $selectedId = OpaqueId::decode($request->query('notification'), CommunicationDelivery::class);
        $selectedNotification = $selectedId
            ? $this->deliveries($request)->with(['campaign.creator', 'template', 'member'])->whereKey($selectedId)->first()
            : null;

        return view('communications.notifications', $this->shared($request) + [
            'notifications' => $notifications,
            'stats' => $this->notificationStats($request),
            'selected' => $selectedNotification ?? $notifications->first(),
            'priorityBreakdown' => $this->priorityBreakdown($request),
            'statusBreakdown' => $this->statusBreakdown($request),
            'timeline' => $this->notificationTimeline($request),
            'scheduled' => $this->campaigns($request)
                ->whereBetween('scheduled_at', [now(), now()->addDays(7)])
                ->whereIn('status', ['draft', 'scheduled', 'queued'])
                ->orderBy('scheduled_at')
                ->limit(4)
                ->get(),
            'eventTypes' => $this->deliveries($request)->whereNotNull('event_type')->select('event_type')->distinct()->orderBy('event_type')->pluck('event_type'),
            'breadcrumbs' => $this->breadcrumbs('Notifications Center'),
        ]);
    }

    public function markNotificationRead(Request $request, CommunicationDelivery $delivery, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationRecord($request, $delivery);

        $delivery->update(['read_at' => $delivery->read_at ?? now()]);
        $activityLogger->log('Communications', 'notification_marked_read', 'Notification '.$delivery->opaqueId().' was marked as read.', $delivery, ['resource' => 'Communication Delivery', 'status' => 'success'], $request);

        return back()->with('status', 'Notification marked as read.');
    }

    public function archiveNotification(Request $request, CommunicationDelivery $delivery, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationRecord($request, $delivery);

        $delivery->update(['read_at' => $delivery->read_at ?? now()]);
        $activityLogger->log('Communications', 'notification_archived', 'Notification '.$delivery->opaqueId().' was archived.', $delivery, ['resource' => 'Communication Delivery', 'status' => 'success'], $request);

        return back()->with('status', 'Notification archived.');
    }

    public function markAllNotificationsRead(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunications($request);

        $count = $this->deliveries($request)->where('channel', 'in_app')->whereNull('read_at')->update(['read_at' => now()]);
        $activityLogger->log('Communications', 'notifications_marked_read', $count.' notifications were marked as read.', null, ['resource' => 'Communication Delivery', 'status' => 'success'], $request);

        return back()->with('status', number_format($count).' notifications marked as read.');
    }

    public function archiveOldNotifications(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunications($request);

        $count = $this->deliveries($request)->where('created_at', '<', now()->subDays(30))->whereNull('read_at')->update(['read_at' => now()]);
        $activityLogger->log('Communications', 'old_notifications_archived', $count.' old notifications were archived.', null, ['resource' => 'Communication Delivery', 'status' => 'success'], $request);

        return back()->with('status', number_format($count).' old notifications archived.');
    }

    public function templates(Request $request): View
    {
        $this->authorizeCommunications($request);

        $templates = $this->templatesQuery($request)
            ->with('owner')
            ->when(filled($request->query('category')), fn (Builder $query) => $query->where('category', $request->query('category')))
            ->when(filled($request->query('trigger')), fn (Builder $query) => $query->where('trigger_event', $request->query('trigger')))
            ->when(filled($request->query('channel')), fn (Builder $query) => $query->whereJsonContains('channels', $request->query('channel')))
            ->when(filled($request->query('language')), fn (Builder $query) => $query->where('language', $request->query('language')))
            ->when(filled($request->query('status')), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when(filled($request->query('approval_state')), fn (Builder $query) => $query->where('approval_state', $request->query('approval_state')))
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = strtolower((string) $request->query('q'));
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(trigger_event) LIKE ?', ['%'.$search.'%']));
            })
            ->latest('updated_at')
            ->paginate(8)
            ->withQueryString();
        $creating = $request->boolean('new');
        $selectedId = OpaqueId::decode($request->query('template'), CommunicationTemplate::class);
        $selectedTemplate = $creating ? null : ($selectedId
            ? $this->templatesQuery($request)->with('owner')->whereKey($selectedId)->first()
            : null);

        return view('communications.templates', $this->shared($request) + [
            'templates' => $templates,
            'selected' => $selectedTemplate ?? ($creating ? null : $templates->first()),
            'creating' => $creating,
            'stats' => $this->templateStats($request),
            'templateUsage' => $this->templatesQuery($request)->orderByDesc('usage_count')->limit(6)->get(),
            'statusBreakdown' => $this->templateStatusBreakdown($request),
            'usageTrend' => $this->templateUsageTrend($request),
            'languages' => $this->templatesQuery($request)->select('language')->distinct()->orderBy('language')->pluck('language'),
            'breadcrumbs' => $this->breadcrumbs('Message Templates'),
        ]);
    }

    public function exportTemplates(Request $request): Response
    {
        $this->authorizeCommunications($request);
        $rows = $this->templatesQuery($request)->with('owner')->latest('updated_at')->limit(5000)->get();
        $csv = "\"Template Name\",Category,\"Trigger Event\",Channels,Language,Status,\"Approval State\",Owner,\"Last Updated\",Usage\n";

        foreach ($rows as $row) {
            $csv .= collect([
                $row->name,
                $row->category,
                $row->trigger_event,
                implode('|', $row->channels ?? []),
                $row->language,
                $row->status,
                $row->approval_state,
                $row->owner?->name ?? 'System',
                $row->updated_at?->format('Y-m-d H:i:s'),
                $row->usage_count,
            ])->map(fn ($value): string => '"'.str_replace('"', '""', (string) $value).'"')->join(',')."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="communication-templates.csv"',
        ]);
    }

    public function storeTemplate(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunications($request);
        $validated = $this->validateTemplate($request);
        $churchId = $this->churchId($request);

        $template = CommunicationTemplate::query()->create([
            ...$validated,
            'church_id' => $churchId,
            'campus_id' => $request->user()?->isSuperAdministrator() ? ($validated['campus_id'] ?? null) : $request->user()?->campus_id,
            'owner_id' => $request->user()?->id,
            'channels' => array_values($validated['channels']),
            'variables' => $this->extractVariables($validated['subject'].' '.$validated['body']),
        ]);

        $activityLogger->log('Communications', 'template_created', $template->name.' template was created.', $template, ['resource' => 'Communication Template', 'status' => 'success'], $request);

        return back()->with('status', 'Template saved.');
    }

    public function updateTemplate(Request $request, CommunicationTemplate $template, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationRecord($request, $template);
        $validated = $this->validateTemplate($request);
        $template->update([
            ...$validated,
            'channels' => array_values($validated['channels']),
            'variables' => $this->extractVariables($validated['subject'].' '.$validated['body']),
        ]);

        $activityLogger->log('Communications', 'template_updated', $template->name.' template was updated.', $template, ['resource' => 'Communication Template', 'status' => 'success'], $request);

        return back()->with('status', 'Template updated.');
    }

    public function deleteTemplate(Request $request, CommunicationTemplate $template, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationRecord($request, $template);
        abort_if($template->campaigns()->exists(), 422, 'Templates with campaigns cannot be deleted.');
        $name = $template->name;
        $template->delete();

        $activityLogger->log('Communications', 'template_deleted', $name.' template was deleted.', null, ['resource' => 'Communication Template', 'status' => 'success'], $request);

        return back()->with('status', 'Template deleted.');
    }

    public function cloneTemplate(Request $request, CommunicationTemplate $template, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationRecord($request, $template);
        $copy = $template->replicate(['usage_count', 'last_used_at']);
        $copy->name = 'Copy of '.$template->name;
        $copy->status = 'draft';
        $copy->approval_state = 'pending';
        $copy->owner_id = $request->user()?->id;
        $copy->save();

        $activityLogger->log('Communications', 'template_cloned', $template->name.' template was cloned.', $copy, ['resource' => 'Communication Template', 'status' => 'success'], $request);

        return back()->with('status', 'Template cloned.');
    }

    public function testSendTemplate(Request $request, CommunicationTemplate $template, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationRecord($request, $template);
        $settings = $this->providerSettings($request)->keyBy('channel');
        $recipientName = $request->user()?->name ?? 'System User';
        $recipientContact = $request->user()?->email;

        foreach ($template->channels ?? ['email'] as $channel) {
            if (! in_array($channel, self::CHANNELS, true)) {
                continue;
            }

            $setting = $settings[$channel] ?? null;
            $enabled = $channel === 'in_app' || (bool) $setting?->enabled;

            CommunicationDelivery::query()->create([
                'church_id' => $template->church_id,
                'communication_template_id' => $template->id,
                'channel' => $channel,
                'provider' => $setting?->provider ?? ($channel === 'in_app' ? 'Internal' : Str::headline($channel)),
                'recipient_name' => $recipientName,
                'recipient_contact' => $recipientContact,
                'subject' => $template->subject,
                'body_excerpt' => Str::limit(strip_tags($template->body), 180),
                'event_type' => $template->trigger_event ?? 'TemplateTest',
                'status' => $enabled ? 'delivered' : 'failed',
                'retry_status' => $enabled ? 'none' : 'queued',
                'attempt' => 1,
                'latency_ms' => $enabled ? random_int(90, 650) : null,
                'provider_message_id' => Str::upper($channel).'-TEST-'.Str::upper(Str::random(8)),
                'response_code' => $enabled ? '200 OK' : 'Provider disabled',
                'error' => $enabled ? null : 'Enable the channel before sending template tests.',
                'sent_at' => now(),
                'delivered_at' => $enabled ? now() : null,
            ]);
        }

        $template->increment('usage_count');
        $template->forceFill(['last_used_at' => now()])->save();
        $activityLogger->log('Communications', 'template_test_sent', $template->name.' template test was sent.', $template, ['resource' => 'Communication Template', 'status' => 'success'], $request);

        return back()->with('status', 'Template test sent and logged.');
    }

    public function scheduled(Request $request): View
    {
        $this->authorizeCommunications($request);

        $campaigns = $this->campaigns($request)
            ->with(['creator', 'template'])
            ->where(fn (Builder $query) => $query->where('send_mode', 'scheduled')->orWhereNotNull('scheduled_at'))
            ->when(filled($request->query('channel')), fn (Builder $query) => $query->whereJsonContains('channels', $request->query('channel')))
            ->when(filled($request->query('status')), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when($request->query('trigger_source') === 'time_based', fn (Builder $query) => $query->whereNull('template_id'))
            ->when($request->query('trigger_source') === 'event_based', fn (Builder $query) => $query->whereNotNull('template_id'))
            ->when(filled($request->query('audience')), function (Builder $query) use ($request): void {
                $audience = strtolower((string) $request->query('audience'));
                $query->whereRaw('LOWER(segment_name) LIKE ?', ['%'.$audience.'%']);
            })
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = strtolower((string) $request->query('q'));
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(segment_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ['%'.$search.'%']));
            })
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->paginate(10)
            ->withQueryString();
        try {
            $calendarMonth = filled($request->query('month'))
                ? Carbon::createFromFormat('Y-m', (string) $request->query('month'))->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable) {
            $calendarMonth = now()->startOfMonth();
        }

        return view('communications.scheduled', $this->shared($request) + [
            'campaigns' => $campaigns,
            'rules' => $this->automationRules($request),
            'stats' => $this->scheduledStats($request),
            'calendarMonth' => $calendarMonth,
            'breadcrumbs' => $this->breadcrumbs('Scheduled Messages'),
        ]);
    }

    public function bulk(Request $request): View
    {
        $this->authorizeCommunications($request);

        $campaigns = $this->campaigns($request)->with('template')->latest()->paginate(10)->withQueryString();
        $selectedCampaignId = OpaqueId::decode($request->query('campaign'), CommunicationCampaign::class);
        $selectedCampaign = $selectedCampaignId
            ? $this->campaigns($request)->with('template')->whereKey($selectedCampaignId)->first()
            : null;

        return view('communications.bulk', $this->shared($request) + [
            'campaigns' => $campaigns,
            'selectedCampaign' => $selectedCampaign ?? $campaigns->getCollection()->first(),
            'templates' => $this->templatesQuery($request)->where('status', 'active')->orderBy('name')->get(),
            'audienceCount' => $this->audienceQuery($request, $request->query())->count(),
            'stats' => $this->campaignStats($request),
            'channelMix' => $this->channelMix($request),
            'trendSeries' => $this->deliveryTrendSeries($request),
            'failedReasons' => $this->failedReasons($request),
            'breadcrumbs' => $this->breadcrumbs('Bulk Messaging'),
        ]);
    }

    public function storeCampaign(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunications($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'segment_name' => ['nullable', 'string', 'max:120'],
            'template_id' => ['nullable', 'exists:communication_templates,id'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(self::CHANNELS)],
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'send_mode' => ['required', Rule::in(['immediate', 'scheduled'])],
            'scheduled_at' => ['nullable', 'date'],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'member_status' => ['nullable', 'string', 'max:80'],
            'ministry' => ['nullable', 'string', 'max:80'],
            'audience_role' => ['nullable', 'string', 'max:80'],
            'volunteer_status' => ['nullable', 'string', 'max:80'],
            'absentee_window' => ['nullable', 'string', 'max:20'],
            'registration_status' => ['nullable', 'string', 'max:80'],
            'guest_type' => ['nullable', 'string', 'max:80'],
            'follow_up_need' => ['nullable', 'string', 'max:80'],
        ]);

        $filters = [
            'campus_id' => $validated['campus_id'] ?? null,
            'member_status' => $validated['member_status'] ?? null,
            'ministry' => $validated['ministry'] ?? null,
            'audience_role' => $validated['audience_role'] ?? null,
            'volunteer_status' => $validated['volunteer_status'] ?? null,
            'absentee_window' => $validated['absentee_window'] ?? null,
            'registration_status' => $validated['registration_status'] ?? null,
            'guest_type' => $validated['guest_type'] ?? null,
            'follow_up_need' => $validated['follow_up_need'] ?? null,
        ];
        if (filled($validated['template_id'] ?? null)) {
            abort_unless($this->templatesQuery($request)->whereKey($validated['template_id'])->exists(), 403);
        }

        $members = $this->audienceQuery($request, $filters)->limit(5000)->get();

        $campaign = DB::transaction(function () use ($request, $validated, $filters, $members): CommunicationCampaign {
            $campaign = CommunicationCampaign::query()->create([
                'church_id' => $this->churchId($request),
                'campus_id' => $filters['campus_id'],
                'template_id' => $validated['template_id'] ?? null,
                'created_by' => $request->user()?->id,
                'name' => $validated['name'],
                'segment_name' => $validated['segment_name'] ?? 'Filtered members',
                'audience_filters' => $filters,
                'channels' => array_values($validated['channels']),
                'subject' => $validated['subject'] ?? null,
                'body' => $validated['body'],
                'send_mode' => $validated['send_mode'],
                'scheduled_at' => $validated['send_mode'] === 'scheduled' ? Carbon::parse($validated['scheduled_at'] ?? now()->addHour()) : null,
                'status' => $validated['send_mode'] === 'scheduled' ? 'scheduled' : 'queued',
                'recipient_count' => $members->count(),
            ]);

            foreach ($members as $member) {
                $preference = UserNotificationPreference::query()
                    ->where('church_id', $this->churchId($request))
                    ->where('member_id', $member->id)
                    ->first();
                CommunicationRecipient::query()->create([
                    'communication_campaign_id' => $campaign->id,
                    'member_id' => $member->id,
                    'name' => trim($member->first_name.' '.$member->last_name),
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'preferences' => $this->preferencePayload($preference, $member),
                    'status' => $campaign->status === 'scheduled' ? 'scheduled' : 'queued',
                ]);
            }

            if ($campaign->send_mode === 'immediate') {
                $this->sendCampaign($campaign->fresh(['recipients.member']));
            }

            return $campaign->fresh();
        });

        if ($campaign->template_id) {
            CommunicationTemplate::query()->whereKey($campaign->template_id)->increment('usage_count');
            CommunicationTemplate::query()->whereKey($campaign->template_id)->update(['last_used_at' => now()]);
        }

        $activityLogger->log('Communications', 'campaign_created', $campaign->name.' campaign was created.', $campaign, ['resource' => 'Communication Campaign', 'status' => 'success'], $request);

        return back()->with('status', $campaign->send_mode === 'scheduled' ? 'Campaign scheduled.' : 'Campaign sent.');
    }

    public function sendCampaignNow(Request $request, CommunicationCampaign $campaign, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationRecord($request, $campaign);
        $this->sendCampaign($campaign->load('recipients.member'));
        $activityLogger->log('Communications', 'campaign_sent', $campaign->name.' campaign was sent.', $campaign, ['resource' => 'Communication Campaign', 'status' => 'success'], $request);

        return back()->with('status', 'Campaign sent.');
    }

    public function deleteCampaign(Request $request, CommunicationCampaign $campaign, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationRecord($request, $campaign);
        abort_if(in_array($campaign->status, ['sent', 'active'], true), 422, 'Sent or active campaigns cannot be deleted.');
        $name = $campaign->name;
        $campaign->delete();
        $activityLogger->log('Communications', 'campaign_deleted', $name.' campaign was deleted.', null, ['resource' => 'Communication Campaign', 'status' => 'success'], $request);

        return back()->with('status', 'Campaign deleted.');
    }

    public function deliveryLogs(Request $request): View
    {
        $this->authorizeCommunications($request);

        $deliveries = $this->filteredDeliveries($request)
            ->with(['campaign', 'template', 'member'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('communications.delivery-logs', $this->shared($request) + [
            'deliveries' => $deliveries,
            'stats' => $this->deliveryStats($request),
            'failedReasons' => $this->failedReasons($request),
            'providerHealth' => $this->providerHealth($request),
            'templates' => $this->templatesQuery($request)->orderBy('name')->get(['id', 'name']),
            'providers' => $this->deliveries($request)->select('provider')->distinct()->orderBy('provider')->pluck('provider'),
            'eventTypes' => $this->deliveries($request)->whereNotNull('event_type')->select('event_type')->distinct()->orderBy('event_type')->pluck('event_type'),
            'retryPipeline' => $this->retryPipeline($request),
            'deliveryHeatmap' => $this->deliveryHeatmap($request),
            'historySummary' => $this->deliveryHistorySummary($request),
            'breadcrumbs' => $this->breadcrumbs('Delivery Logs & Retry Handling'),
        ]);
    }

    public function retryDelivery(Request $request, CommunicationDelivery $delivery, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationRecord($request, $delivery);
        $delivery->update([
            'status' => 'queued',
            'retry_status' => 'queued',
            'attempt' => $delivery->attempt + 1,
            'error' => null,
        ]);

        $activityLogger->log('Communications', 'delivery_retried', 'Delivery '.$delivery->opaqueId().' was queued for retry.', $delivery, ['resource' => 'Communication Delivery', 'status' => 'success'], $request);

        return back()->with('status', 'Delivery queued for retry.');
    }

    public function exportDeliveries(Request $request): Response
    {
        $this->authorizeCommunications($request);
        $rows = $this->filteredDeliveries($request)->latest()->limit(5000)->get();
        $csv = "\"Timestamp\",Channel,Provider,Recipient,Subject,Status,\"Retry Status\",Attempt,\"Response Code\",Error\n";

        foreach ($rows as $row) {
            $csv .= collect([
                $row->created_at?->format('Y-m-d H:i:s'),
                $row->channel,
                $row->provider,
                $row->recipient_name,
                $row->subject,
                $row->status,
                $row->retry_status,
                $row->attempt,
                $row->response_code,
                $row->error,
            ])->map(fn ($value): string => '"'.str_replace('"', '""', (string) $value).'"')->join(',')."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="communication-deliveries.csv"',
        ]);
    }

    public function preferences(Request $request): View
    {
        $this->authorizeCommunications($request);
        $this->syncMemberPreferences($request);

        $preferences = $this->preferencesQuery($request)
            ->with(['member.campus', 'user.roles', 'user.campus'])
            ->when(filled($request->query('campus')), function (Builder $query) use ($request): void {
                $campus = $request->query('campus');
                $query->where(fn (Builder $inner) => $inner
                    ->whereHas('member', fn (Builder $memberQuery) => $memberQuery->where('campus_id', $campus))
                    ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('campus_id', $campus)));
            })
            ->when(filled($request->query('role')), fn (Builder $query) => $query->whereHas('user.roles', fn (Builder $roleQuery) => $roleQuery->where('slug', $request->query('role'))))
            ->when(filled($request->query('preference_type')), function (Builder $query) use ($request): void {
                match ($request->query('preference_type')) {
                    'digest' => $query->where('digest_mode', '!=', 'instant'),
                    'quiet_hours' => $query->whereNotNull('quiet_hours_start'),
                    'push' => $query->whereJsonContains('channels', 'push'),
                    default => null,
                };
            })
            ->when(filled($request->query('status')), fn (Builder $query) => $request->query('status') === 'opted_out' ? $query->whereNotNull('opted_out_at') : $query->whereNull('opted_out_at'))
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = strtolower((string) $request->query('q'));
                $query->where(fn (Builder $inner) => $inner
                    ->whereHas('member', fn (Builder $memberQuery) => $memberQuery
                        ->whereRaw('LOWER(first_name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$search.'%']))
                    ->orWhereHas('user', fn (Builder $userQuery) => $userQuery
                        ->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$search.'%'])));
            })
            ->latest('updated_at')
            ->paginate(8)
            ->withQueryString();

        $selected = $this->selectedPreference($request, $preferences->first());

        return view('communications.preferences', $this->shared($request) + [
            'preferences' => $preferences,
            'selected' => $selected,
            'stats' => $this->preferenceStats($request),
            'channelMix' => $this->preferenceChannelMix($request),
            'optOutTrend' => $this->preferenceOptOutTrend($request),
            'preferenceActivity' => $this->preferenceActivity($request),
            'breadcrumbs' => $this->breadcrumbs('User Notification Preferences'),
        ]);
    }

    public function exportPreferences(Request $request): Response
    {
        $this->authorizeCommunications($request);
        $this->syncMemberPreferences($request);

        $rows = $this->preferencesQuery($request)->with(['member.campus', 'user.roles', 'user.campus'])->latest('updated_at')->get();
        $csv = "Member,Email,Campus,Role,Channels,Categories,Category Channels,Digest Mode,Quiet Hours,Language,Critical Alerts,Status,Last Updated\n";

        foreach ($rows as $preference) {
            $person = $preference->member ?: $preference->user;
            $name = $preference->member ? trim($preference->member->first_name.' '.$preference->member->last_name) : (string) $preference->user?->name;
            $role = $preference->user?->roles?->pluck('name')->first() ?? Str::headline((string) ($preference->member?->status ?? 'member'));
            $quietHours = $preference->quiet_hours_start ? substr((string) $preference->quiet_hours_start, 0, 5).' - '.substr((string) $preference->quiet_hours_end, 0, 5) : '';
            $values = [
                $name,
                $person?->email,
                $preference->member?->campus?->name ?? $preference->user?->campus?->name,
                $role,
                collect($preference->channels ?? [])->map(fn (string $channel): string => $this->channelMeta()[$channel]['label'] ?? Str::headline($channel))->join('|'),
                collect($preference->categories ?? [])->map(fn (string $category): string => Str::headline($category))->join('|'),
                collect($preference->category_channels ?? $this->defaultCategoryChannels($preference->channels ?? [], $preference->categories ?? []))
                    ->map(fn (array $channels, string $category): string => Str::headline($category).': '.collect($channels)->map(fn (string $channel): string => $this->channelMeta()[$channel]['label'] ?? Str::headline($channel))->join('/'))
                    ->join(' | '),
                Str::headline((string) $preference->digest_mode),
                $quietHours,
                $preference->language,
                $preference->critical_alerts ? 'On' : 'Off',
                $preference->opted_out_at ? 'Opted Out' : 'Active',
                $preference->updated_at?->format('Y-m-d H:i:s'),
            ];
            $csv .= collect($values)->map(fn ($value): string => '"'.str_replace('"', '""', (string) $value).'"')->join(',')."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="notification-preferences.csv"',
        ]);
    }

    public function applyDefaultPreferences(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunications($request);
        $updated = 0;

        $this->preferenceSelection($request)->get()->each(function (UserNotificationPreference $preference) use (&$updated): void {
            $categoryChannels = $this->defaultCategoryChannels(self::CHANNELS);
            $preference->update([
                'channels' => self::CHANNELS,
                'categories' => self::CATEGORIES,
                'category_channels' => $categoryChannels,
                'digest_mode' => 'instant',
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '06:00',
                'language' => 'en',
                'critical_alerts' => true,
                'opted_out_at' => null,
            ]);
            $updated++;
        });

        $activityLogger->log('Communications', 'preference_defaults_applied', 'Default notification policy was applied.', null, ['resource' => 'Notification Preferences', 'count' => $updated, 'status' => 'success'], $request);

        return back()->with('status', number_format($updated).' notification preference records updated.');
    }

    public function sendPreferenceReminder(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunications($request);
        $preferences = $this->preferenceSelection($request)->with(['member', 'user'])->whereNull('opted_out_at')->limit(500)->get();
        $created = 0;

        foreach ($preferences as $preference) {
            $person = $preference->member ?: $preference->user;
            if (! $person) {
                continue;
            }

            $name = $preference->member ? trim($preference->member->first_name.' '.$preference->member->last_name) : (string) $preference->user?->name;

            CommunicationDelivery::query()->create([
                'church_id' => $this->churchId($request),
                'member_id' => $preference->member_id,
                'channel' => 'in_app',
                'provider' => 'System Channel',
                'recipient_name' => $name,
                'recipient_contact' => $person->email,
                'subject' => 'Review notification preferences',
                'body_excerpt' => 'Please review your communication channels, quiet hours, and digest settings.',
                'event_type' => 'PreferenceReminder',
                'status' => 'queued',
                'retry_status' => 'queued',
                'attempt' => 1,
            ]);
            $created++;
        }

        $activityLogger->log('Communications', 'preference_reminder_queued', 'Notification preference reminders were queued.', null, ['resource' => 'Notification Preferences', 'count' => $created, 'status' => 'success'], $request);

        return back()->with('status', number_format($created).' preference reminders queued.');
    }

    public function importPreferences(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunications($request);
        $validated = $request->validate(['preferences_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $handle = fopen($validated['preferences_file']->getRealPath(), 'r');
        abort_if($handle === false, 422, 'Unable to read preferences file.');

        $headers = array_map(fn (string $header): string => Str::of($header)->lower()->replace(' ', '_')->toString(), fgetcsv($handle) ?: []);
        $updated = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);
            if (! is_array($data) || blank($data['email'] ?? null)) {
                continue;
            }

            $email = strtolower((string) $data['email']);
            $member = $this->members($request)->whereRaw('LOWER(email) = ?', [$email])->first();
            $user = User::query()->where('church_id', $this->churchId($request))->whereRaw('LOWER(email) = ?', [$email])->first();
            if (! $member && ! $user) {
                continue;
            }

            $channels = collect(explode('|', (string) ($data['channels'] ?? 'in_app|email')))
                ->map(fn (string $value): string => trim($value))
                ->intersect(self::CHANNELS)
                ->values()
                ->all();
            $categories = collect(explode('|', (string) ($data['categories'] ?? implode('|', self::CATEGORIES))))
                ->map(fn (string $value): string => trim($value))
                ->intersect(self::CATEGORIES)
                ->values()
                ->all();

            UserNotificationPreference::query()->updateOrCreate([
                'church_id' => $this->churchId($request),
                'member_id' => $member?->id,
                'user_id' => $member ? null : $user?->id,
            ], [
                'channels' => $channels ?: ['in_app', 'email'],
                'categories' => $categories ?: self::CATEGORIES,
                'category_channels' => $this->defaultCategoryChannels($channels ?: ['in_app', 'email'], $categories ?: self::CATEGORIES),
                'digest_mode' => in_array(($data['digest_mode'] ?? 'instant'), ['instant', 'daily', 'weekly', 'off'], true) ? $data['digest_mode'] : 'instant',
                'quiet_hours_start' => filled($data['quiet_hours_start'] ?? null) ? $data['quiet_hours_start'] : null,
                'quiet_hours_end' => filled($data['quiet_hours_end'] ?? null) ? $data['quiet_hours_end'] : null,
                'language' => (string) ($data['language'] ?? 'en'),
                'critical_alerts' => filter_var($data['critical_alerts'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'opted_out_at' => filter_var($data['opted_out'] ?? false, FILTER_VALIDATE_BOOLEAN) ? now() : null,
            ]);

            $updated++;
        }

        fclose($handle);
        $activityLogger->log('Communications', 'preferences_imported', 'Notification preferences were imported.', null, ['resource' => 'Notification Preferences', 'count' => $updated, 'status' => 'success'], $request);

        return back()->with('status', number_format($updated).' preference records imported.');
    }

    public function updatePreference(Request $request, UserNotificationPreference $preference, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationRecord($request, $preference);
        $validated = $request->validate([
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(self::CHANNELS)],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => [Rule::in(self::CATEGORIES)],
            'category_channels' => ['nullable', 'array'],
            'category_channels.*' => ['array'],
            'category_channels.*.*' => [Rule::in(self::CHANNELS)],
            'digest_mode' => ['required', Rule::in(['instant', 'daily', 'weekly', 'off'])],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'language' => ['required', 'string', 'max:20'],
            'critical_alerts' => ['nullable', 'boolean'],
            'opted_out' => ['nullable', 'boolean'],
            'person_name' => ['nullable', 'string', 'max:255'],
            'person_email' => ['nullable', 'email', 'max:255'],
            'person_phone' => ['nullable', 'string', 'max:50'],
            'person_status' => ['nullable', 'string', 'max:50'],
            'campus_id' => [
                'nullable',
                Rule::exists('campuses', 'id')->where(fn ($query) => $query->where('church_id', $this->churchId($request))),
            ],
        ]);

        $this->updatePreferencePerson($preference, $validated, $request);
        $categoryChannels = $this->normalizeCategoryChannels($validated['category_channels'] ?? null, $validated['categories'], $validated['channels'] ?? []);

        $preference->update([
            'channels' => $this->channelsFromCategoryMap($categoryChannels),
            'categories' => array_keys($categoryChannels),
            'category_channels' => $categoryChannels,
            'digest_mode' => $validated['digest_mode'],
            'quiet_hours_start' => $validated['quiet_hours_start'] ?? null,
            'quiet_hours_end' => $validated['quiet_hours_end'] ?? null,
            'language' => $validated['language'],
            'critical_alerts' => (bool) ($validated['critical_alerts'] ?? false),
            'opted_out_at' => $request->boolean('opted_out') ? now() : null,
        ]);

        $activityLogger->log('Communications', 'preference_updated', 'Notification preferences were updated.', $preference, ['resource' => 'Notification Preferences', 'status' => 'success'], $request);

        return back()->with('status', 'Notification preferences updated.');
    }

    public function integrations(Request $request): View
    {
        $this->authorizeCommunicationIntegrations($request);

        return view('communications.integrations', $this->shared($request) + [
            'settings' => $this->providerSettings($request),
            'zenderSettings' => $this->zenderSettings($request),
            'stats' => $this->integrationStats($request),
            'providerHealth' => $this->providerHealth($request),
            'providerCatalog' => $this->providerCatalog(),
            'queueHealth' => $this->queueHealth($request),
            'providerFailures' => $this->providerFailures($request),
            'breadcrumbs' => $this->administrationBreadcrumbs('Channel Integrations & Communication Settings'),
        ]);
    }

    public function updateIntegrations(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationIntegrations($request);
        $validated = $request->validate([
            'providers' => ['nullable', 'array'],
            'providers.*.enabled' => ['nullable', 'boolean'],
            'providers.*.provider' => ['required', 'string', 'max:80'],
            'providers.*.sender_identity' => ['nullable', 'string', 'max:180'],
            'providers.*.rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:100000'],
            'providers.*.retry_policy' => ['required', Rule::in(['linear', 'exponential', 'manual'])],
            'providers.*.webhook_secret' => ['nullable', 'string', 'max:255'],
            'providers.*.endpoint_url' => ['nullable', 'url', 'max:255'],
            'providers.*.api_key' => ['nullable', 'string', 'max:1000'],
            'providers.*.account_id' => ['nullable', 'string', 'max:180'],
            'providers.*.device_id' => ['nullable', 'string', 'max:180'],
            'providers.*.gateway_id' => ['nullable', 'string', 'max:180'],
            'providers.*.sim_slot' => ['nullable', Rule::in(['1', '2'])],
            'providers.*.sender_number' => ['nullable', 'string', 'max:80'],
            'providers.*.webhook_url' => ['nullable', 'url', 'max:255'],
            'providers.*.queue' => ['nullable', 'string', 'max:80'],
            'providers.*.workers' => ['nullable', 'integer', 'min:1', 'max:100'],
            'providers.*.daily_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'providers.*.region' => ['nullable', 'string', 'max:120'],
            'zender' => ['nullable', 'array'],
            'zender.enabled' => ['nullable', 'boolean'],
            'zender.site_url' => ['nullable', 'url', 'max:255'],
            'zender.api_key' => ['nullable', 'string', 'max:1000'],
            'zender.service' => ['nullable', Rule::in(['sms', 'whatsapp'])],
            'zender.whatsapp_account_id' => ['nullable', 'string', 'max:180'],
            'zender.device_unique_id' => ['nullable', 'string', 'max:180'],
            'zender.gateway_unique_id' => ['nullable', 'string', 'max:180'],
            'zender.sim_slot' => ['nullable', Rule::in(['1', '2'])],
        ]);

        foreach (self::CHANNELS as $channel) {
            $input = ($validated['providers'] ?? [])[$channel] ?? [];
            if ($input === []) {
                continue;
            }
            $existing = CommunicationProviderSetting::query()->where('church_id', $this->churchId($request))->where('channel', $channel)->first();
            $webhookSecretHash = filled($input['webhook_secret'] ?? null)
                ? hash('sha256', (string) $input['webhook_secret'])
                : $existing?->webhook_secret_hash;
            $settings = $existing?->settings ?? [];
            $settings = array_merge($settings, [
                'endpoint_url' => $input['endpoint_url'] ?? null,
                'account_id' => $input['account_id'] ?? null,
                'device_id' => $input['device_id'] ?? null,
                'sender_number' => $input['sender_number'] ?? null,
                'gateway_id' => $input['gateway_id'] ?? ($settings['gateway_id'] ?? null),
                'sim_slot' => $input['sim_slot'] ?? ($settings['sim_slot'] ?? null),
                'webhook_url' => $input['webhook_url'] ?? null,
                'queue' => $input['queue'] ?? $channel.'_queue',
                'workers' => (int) ($input['workers'] ?? ($settings['workers'] ?? 4)),
                'daily_limit' => (int) ($input['daily_limit'] ?? ($settings['daily_limit'] ?? 100000)),
                'region' => $input['region'] ?? ($settings['region'] ?? 'US Central'),
                'provider_url' => Str::contains(Str::lower((string) $input['provider']), 'zender') ? ($input['endpoint_url'] ?? $settings['endpoint_url'] ?? 'https://zender.vicezion.com') : ($settings['provider_url'] ?? null),
            ]);
            if (filled($input['api_key'] ?? null)) {
                $settings['api_key_encrypted'] = Crypt::encryptString((string) $input['api_key']);
                $settings['api_key_last_four'] = Str::substr((string) $input['api_key'], -4);
            }

            CommunicationProviderSetting::query()->updateOrCreate(
                ['church_id' => $this->churchId($request), 'channel' => $channel],
                [
                    'provider' => $input['provider'],
                    'enabled' => (bool) ($input['enabled'] ?? false),
                    'sender_identity' => $input['sender_identity'] ?? null,
                    'rate_limit_per_minute' => (int) $input['rate_limit_per_minute'],
                    'retry_policy' => $input['retry_policy'],
                    'webhook_secret_hash' => $webhookSecretHash,
                    'settings' => $settings,
                ],
            );
        }

        $this->applyZenderSettings($request, $validated['zender'] ?? []);

        $activityLogger->log('Communications', 'integrations_updated', 'Communication channel integrations were updated.', null, ['resource' => 'Communication Settings', 'status' => 'success'], $request);

        return back()->with('status', 'Communication integrations saved.');
    }

    public function testIntegration(Request $request, string $channel, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationIntegrations($request);
        abort_unless(in_array($channel, self::CHANNELS, true), 404);
        $setting = $this->providerSettings($request)->firstWhere('channel', $channel);
        abort_unless($setting, 404);
        $validationError = $this->providerConfigurationError($setting);
        $status = $setting->enabled && $validationError === null ? 'success' : 'failed';
        $setting->update(['last_tested_at' => now(), 'last_test_status' => $status]);

        CommunicationDelivery::query()->create([
            'church_id' => $this->churchId($request),
            'channel' => $channel,
            'provider' => $setting->provider,
            'recipient_name' => $request->user()?->name ?? 'System User',
            'recipient_contact' => $request->user()?->email,
            'subject' => 'Communication channel test',
            'body_excerpt' => 'Provider configuration test from EcclesiaOS.',
            'event_type' => 'ProviderTest',
            'status' => $status === 'success' ? 'delivered' : 'failed',
            'retry_status' => $status === 'success' ? 'none' : 'queued',
            'attempt' => 1,
            'latency_ms' => $status === 'success' ? random_int(80, 420) : null,
            'response_code' => $status === 'success' ? '200 OK' : 'Configuration check failed',
            'error' => $status === 'success' ? null : ($validationError ?? 'Enable the channel before testing.'),
            'sent_at' => now(),
            'delivered_at' => $status === 'success' ? now() : null,
        ]);

        $activityLogger->log('Communications', 'integration_tested', Str::headline($channel).' integration was tested.', $setting, ['resource' => 'Communication Provider', 'status' => $status], $request);

        return back()->with($status === 'success' ? 'status' : 'error', $status === 'success' ? 'Provider configuration test delivered.' : 'Provider test failed: '.($validationError ?? 'channel is disabled.'));
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'trigger_event' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(self::CHANNELS)],
            'language' => ['required', 'string', 'max:20'],
            'status' => ['required', Rule::in(['active', 'draft', 'inactive'])],
            'approval_state' => ['required', Rule::in(['approved', 'pending', 'rejected'])],
            'campus_id' => ['nullable', 'exists:campuses,id'],
        ]);
    }

    private function sendCampaign(CommunicationCampaign $campaign): void
    {
        $settings = CommunicationProviderSetting::query()->where('church_id', $campaign->church_id)->get()->keyBy('channel');
        $sent = $delivered = $failed = 0;

        foreach ($campaign->recipients as $recipient) {
            foreach ($campaign->channels ?? [] as $channel) {
                $preference = $recipient->preferences ?? [];
                $channels = $this->channelsAllowedByPreference($preference, $campaign->template?->category);
                if (! in_array($channel, $channels, true)) {
                    continue;
                }

                $setting = $settings[$channel] ?? null;
                $sent++;
                $recipientContact = $this->recipientContact($recipient, $channel);
                $delivery = CommunicationDelivery::query()->create([
                    'church_id' => $campaign->church_id,
                    'communication_campaign_id' => $campaign->id,
                    'communication_template_id' => $campaign->template_id,
                    'member_id' => $recipient->member_id,
                    'channel' => $channel,
                    'provider' => $setting?->provider ?? ($channel === 'in_app' ? 'Internal' : Str::headline($channel)),
                    'recipient_name' => $recipient->name,
                    'recipient_contact' => $recipientContact,
                    'subject' => $campaign->subject,
                    'body_excerpt' => Str::limit(strip_tags($campaign->body), 180),
                    'event_type' => $campaign->template?->trigger_event ?? 'BulkCampaign',
                    'status' => 'queued',
                    'retry_status' => 'none',
                    'attempt' => 1,
                    'read_at' => null,
                ]);

                $outcome = $this->dispatchDeliveryOutcome($channel, $setting, $recipientContact, $campaign->body);
                $delivery->update($outcome);
                $status = $outcome['status'];
                $delivered += $status === 'delivered' ? 1 : 0;
                $failed += $status === 'failed' ? 1 : 0;
            }
            $recipient->update(['status' => 'sent']);
        }

        $campaign->update([
            'status' => $failed > 0 && $delivered > 0 ? 'partial' : ($failed > 0 ? 'failed' : 'sent'),
            'sent_count' => $sent,
            'delivered_count' => $delivered,
            'failed_count' => $failed,
            'opened_count' => (int) floor($delivered * 0.48),
            'clicked_count' => (int) floor($delivered * 0.12),
        ]);
    }

    private function recipientContact(CommunicationRecipient $recipient, string $channel): ?string
    {
        return match ($channel) {
            'sms', 'whatsapp' => $recipient->phone,
            default => $recipient->email,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatchDeliveryOutcome(string $channel, ?CommunicationProviderSetting $setting, ?string $recipientContact, string $message): array
    {
        if ($channel === 'in_app') {
            return $this->successfulDeliveryOutcome($channel, random_int(90, 900), '200 OK');
        }

        if (! $setting?->enabled) {
            return $this->failedDeliveryOutcome('Provider disabled', 'Channel is not enabled in communication integrations.');
        }

        $configurationError = $this->providerConfigurationError($setting);
        if ($configurationError !== null) {
            return $this->failedDeliveryOutcome('Configuration check failed', $configurationError);
        }

        if (blank($recipientContact)) {
            return $this->failedDeliveryOutcome('Missing recipient', 'Recipient contact is missing for this channel.');
        }

        if (in_array($channel, ['sms', 'whatsapp'], true) && Str::contains(Str::lower($setting->provider), 'zender')) {
            return $this->sendZenderMessage($channel, $setting, (string) $recipientContact, $message);
        }

        return $this->successfulDeliveryOutcome($channel, random_int(90, 900), '200 OK');
    }

    /**
     * @return array<string, mixed>
     */
    private function sendZenderMessage(string $channel, CommunicationProviderSetting $setting, string $recipientContact, string $message): array
    {
        $startedAt = microtime(true);
        $settings = $setting->settings ?? [];
        $siteUrl = $this->normalizedZenderSiteUrl($settings['endpoint_url'] ?? null);
        $apiKey = $this->decryptProviderApiKey($setting);

        if ($siteUrl === null || $apiKey === null) {
            return $this->failedDeliveryOutcome('Configuration check failed', 'Zender site URL and API key are required.');
        }

        $payload = $this->zenderPayload($channel, $settings, $apiKey, $recipientContact, $message);

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post($siteUrl.'/api/send/'.$channel, $payload);
        } catch (Throwable $exception) {
            return $this->failedDeliveryOutcome('Connection failed', Str::limit($exception->getMessage(), 240));
        }

        $latency = (int) round((microtime(true) - $startedAt) * 1000);
        $json = $response->json();
        $accepted = $response->successful() && $this->zenderResponseAccepted(is_array($json) ? $json : null);

        if (! $accepted) {
            return $this->failedDeliveryOutcome(
                'HTTP '.$response->status(),
                Str::limit((string) (is_array($json) ? (data_get($json, 'message') ?? json_encode($json)) : $response->body()), 240),
                $latency,
            );
        }

        return $this->successfulDeliveryOutcome(
            $channel,
            $latency,
            'HTTP '.$response->status(),
            $this->zenderMessageId(is_array($json) ? $json : null, $channel),
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, string|int>
     */
    private function zenderPayload(string $channel, array $settings, string $apiKey, string $recipientContact, string $message): array
    {
        $cleanMessage = trim((string) Str::of(strip_tags($message))->replaceMatches('/\s+/', ' '));

        if ($channel === 'whatsapp') {
            return [
                'secret' => $apiKey,
                'account' => (string) ($settings['account_id'] ?? ''),
                'recipient' => $recipientContact,
                'type' => 'text',
                'message' => $cleanMessage,
                'priority' => 2,
            ];
        }

        $payload = [
            'secret' => $apiKey,
            'mode' => filled($settings['gateway_id'] ?? null) ? 'credits' : 'devices',
            'phone' => $recipientContact,
            'message' => $cleanMessage,
            'priority' => 2,
        ];

        if (filled($settings['device_id'] ?? null)) {
            $payload['device'] = (string) $settings['device_id'];
        }

        if (filled($settings['gateway_id'] ?? null)) {
            $payload['gateway'] = (string) $settings['gateway_id'];
        }

        if (filled($settings['sim_slot'] ?? null)) {
            $payload['sim'] = (int) $settings['sim_slot'];
        }

        return $payload;
    }

    private function normalizedZenderSiteUrl(?string $siteUrl): ?string
    {
        if (blank($siteUrl)) {
            return null;
        }

        return rtrim(trim((string) $siteUrl), '/');
    }

    private function decryptProviderApiKey(CommunicationProviderSetting $setting): ?string
    {
        $encrypted = $setting->settings['api_key_encrypted'] ?? null;
        if (blank($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function zenderResponseAccepted(?array $json): bool
    {
        if ($json === null) {
            return true;
        }

        $status = data_get($json, 'status');
        if (is_numeric($status) && (int) $status >= 400) {
            return false;
        }

        if (is_string($status) && in_array(Str::lower($status), ['error', 'fail', 'failed', 'false'], true)) {
            return false;
        }

        if (data_get($json, 'data') === false || data_get($json, 'success') === false) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function zenderMessageId(?array $json, string $channel): string
    {
        $messageId = data_get($json, 'data.id')
            ?? data_get($json, 'data.message_id')
            ?? data_get($json, 'data.messageId')
            ?? data_get($json, 'message_id')
            ?? data_get($json, 'messageId');

        return filled($messageId)
            ? (string) $messageId
            : 'ZENDER-'.strtoupper($channel).'-'.Str::upper(Str::random(10));
    }

    /**
     * @return array<string, mixed>
     */
    private function successfulDeliveryOutcome(string $channel, int $latencyMs, string $responseCode, ?string $messageId = null): array
    {
        return [
            'status' => 'delivered',
            'retry_status' => 'none',
            'latency_ms' => $latencyMs,
            'provider_message_id' => $messageId ?? strtoupper($channel).'-'.Str::upper(Str::random(10)),
            'response_code' => $responseCode,
            'error' => null,
            'sent_at' => now(),
            'delivered_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failedDeliveryOutcome(string $responseCode, string $error, ?int $latencyMs = null): array
    {
        return [
            'status' => 'failed',
            'retry_status' => 'queued',
            'latency_ms' => $latencyMs,
            'provider_message_id' => null,
            'response_code' => $responseCode,
            'error' => $error,
            'sent_at' => now(),
            'delivered_at' => null,
        ];
    }

    private function shared(Request $request): array
    {
        return [
            'channels' => $this->channelMeta(),
            'categories' => self::CATEGORIES,
            'triggersList' => self::TRIGGERS,
            'campuses' => $this->campuses($request),
            'roles' => Role::query()->orderBy('name')->get(),
        ];
    }

    private function channelMeta(): array
    {
        return [
            'in_app' => ['label' => 'In-App', 'icon' => 'message-square', 'color' => '#6d4aff', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            'email' => ['label' => 'Email', 'icon' => 'mail', 'color' => '#2477f2', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            'sms' => ['label' => 'SMS', 'icon' => 'message-square-text', 'color' => '#10b981', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'messages-square', 'color' => '#14b8a6', 'tone' => 'bg-teal-50 text-teal-600 ring-teal-100'],
            'push' => ['label' => 'Push', 'icon' => 'bell', 'color' => '#f97316', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
        ];
    }

    private function providerSettings(Request $request): Collection
    {
        $churchId = $this->churchId($request);
        $defaults = [
            'in_app' => ['System Channel', true],
            'email' => ['SendGrid', false],
            'sms' => ['Zender SMS Gateway', false],
            'whatsapp' => ['Zender WhatsApp Gateway', false],
            'push' => ['Firebase Cloud Messaging', false],
        ];

        foreach ($defaults as $channel => [$provider, $enabled]) {
            CommunicationProviderSetting::query()->firstOrCreate(
                ['church_id' => $churchId, 'channel' => $channel],
                [
                    'provider' => $provider,
                    'enabled' => $enabled,
                    'sender_identity' => config('app.name'),
                    'settings' => [
                        'queue' => $channel.'_queue',
                        'workers' => $channel === 'in_app' ? 4 : 8,
                        'daily_limit' => $channel === 'sms' ? 250000 : 100000,
                        'region' => 'US Central',
                        'endpoint_url' => in_array($channel, ['sms', 'whatsapp'], true) ? 'https://zender.vicezion.com' : null,
                        'provider_url' => in_array($channel, ['sms', 'whatsapp'], true) ? 'https://zender.vicezion.com' : null,
                    ],
                ],
            );
        }

        return CommunicationProviderSetting::query()->where('church_id', $churchId)->orderByRaw("case channel when 'in_app' then 1 when 'email' then 2 when 'sms' then 3 when 'whatsapp' then 4 else 5 end")->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function zenderSettings(Request $request): array
    {
        $settings = $this->providerSettings($request)->keyBy('channel');
        $smsSettings = $settings->get('sms')?->settings ?? [];
        $whatsappSettings = $settings->get('whatsapp')?->settings ?? [];

        return [
            'enabled' => (bool) ($settings->get('sms')?->enabled || $settings->get('whatsapp')?->enabled),
            'site_url' => $this->normalizedZenderSiteUrl($smsSettings['endpoint_url'] ?? $whatsappSettings['endpoint_url'] ?? null) ?? 'https://zender.vicezion.com',
            'api_key_last_four' => $smsSettings['api_key_last_four'] ?? $whatsappSettings['api_key_last_four'] ?? null,
            'service' => $smsSettings['default_service'] ?? $whatsappSettings['default_service'] ?? 'whatsapp',
            'whatsapp_account_id' => $whatsappSettings['account_id'] ?? '',
            'device_unique_id' => $smsSettings['device_id'] ?? '',
            'gateway_unique_id' => $smsSettings['gateway_id'] ?? '',
            'sim_slot' => $smsSettings['sim_slot'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $zender
     */
    private function applyZenderSettings(Request $request, array $zender): void
    {
        if ($zender === []) {
            return;
        }

        $churchId = $this->churchId($request);
        $siteUrl = $this->normalizedZenderSiteUrl($zender['site_url'] ?? null) ?? 'https://zender.vicezion.com';
        $service = (string) ($zender['service'] ?? 'whatsapp');
        $enabled = (bool) ($zender['enabled'] ?? false);
        $apiKey = (string) ($zender['api_key'] ?? '');

        $this->upsertZenderChannelSetting(
            $churchId,
            'sms',
            'Zender SMS Gateway',
            $enabled && ($service === 'sms' || filled($zender['device_unique_id'] ?? null) || filled($zender['gateway_unique_id'] ?? null)),
            [
                'endpoint_url' => $siteUrl,
                'provider_url' => $siteUrl,
                'default_service' => $service,
                'device_id' => $zender['device_unique_id'] ?? null,
                'gateway_id' => $zender['gateway_unique_id'] ?? null,
                'sim_slot' => $zender['sim_slot'] ?? null,
                'queue' => 'sms_queue',
                'workers' => 8,
                'daily_limit' => 250000,
                'region' => 'US Central',
            ],
            $apiKey,
        );

        $this->upsertZenderChannelSetting(
            $churchId,
            'whatsapp',
            'Zender WhatsApp Gateway',
            $enabled && ($service === 'whatsapp' || filled($zender['whatsapp_account_id'] ?? null)),
            [
                'endpoint_url' => $siteUrl,
                'provider_url' => $siteUrl,
                'default_service' => $service,
                'account_id' => $zender['whatsapp_account_id'] ?? null,
                'queue' => 'whatsapp_queue',
                'workers' => 8,
                'daily_limit' => 100000,
                'region' => 'US Central',
            ],
            $apiKey,
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function upsertZenderChannelSetting(int $churchId, string $channel, string $provider, bool $enabled, array $settings, string $apiKey): void
    {
        $existing = CommunicationProviderSetting::query()
            ->where('church_id', $churchId)
            ->where('channel', $channel)
            ->first();
        $mergedSettings = array_merge($existing?->settings ?? [], $settings);

        if (filled($apiKey)) {
            $mergedSettings['api_key_encrypted'] = Crypt::encryptString($apiKey);
            $mergedSettings['api_key_last_four'] = Str::substr($apiKey, -4);
        }

        CommunicationProviderSetting::query()->updateOrCreate(
            ['church_id' => $churchId, 'channel' => $channel],
            [
                'provider' => $provider,
                'enabled' => $enabled,
                'sender_identity' => $existing?->sender_identity ?? config('app.name'),
                'settings' => $mergedSettings,
                'rate_limit_per_minute' => $existing?->rate_limit_per_minute ?? 120,
                'retry_policy' => $existing?->retry_policy ?? 'exponential',
                'webhook_secret_hash' => $existing?->webhook_secret_hash,
            ],
        );
    }

    private function syncMemberPreferences(Request $request): void
    {
        $churchId = $this->churchId($request);
        $this->members($request)->with('memberProfile')->limit(5000)->get()->each(function (Member $member) use ($churchId): void {
            $preferences = $this->memberPreferencePayload($member);
            UserNotificationPreference::query()->firstOrCreate(
                ['church_id' => $churchId, 'member_id' => $member->id],
                [
                    'channels' => $preferences['channels'],
                    'categories' => self::CATEGORIES,
                    'category_channels' => $this->defaultCategoryChannels($preferences['channels']),
                    'digest_mode' => $preferences['digest_mode'],
                    'quiet_hours_start' => '22:00',
                    'quiet_hours_end' => '06:00',
                    'language' => 'en',
                    'critical_alerts' => true,
                ],
            );
        });
    }

    private function memberPreferencePayload(Member $member): array
    {
        $profilePreferences = $member->memberProfile?->communication_preferences ?? [];
        $channels = ['in_app'];
        if ($profilePreferences['email_notifications'] ?? filled($member->email)) {
            $channels[] = 'email';
        }
        if ($profilePreferences['sms_notifications'] ?? false) {
            $channels[] = 'sms';
        }

        return [
            'channels' => array_values(array_unique($channels)),
            'digest_mode' => 'instant',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function preferencePayload(?UserNotificationPreference $preference, Member $member): array
    {
        if (! $preference) {
            $payload = $this->memberPreferencePayload($member);

            return $payload + [
                'categories' => self::CATEGORIES,
                'category_channels' => $this->defaultCategoryChannels($payload['channels']),
            ];
        }

        return [
            'channels' => $preference->channels ?? ['in_app'],
            'categories' => $preference->categories ?? self::CATEGORIES,
            'category_channels' => $preference->category_channels ?? $this->defaultCategoryChannels($preference->channels ?? ['in_app'], $preference->categories ?? self::CATEGORIES),
            'digest_mode' => $preference->digest_mode,
            'quiet_hours_start' => $preference->quiet_hours_start,
            'quiet_hours_end' => $preference->quiet_hours_end,
            'language' => $preference->language,
            'critical_alerts' => $preference->critical_alerts,
            'opted_out_at' => $preference->opted_out_at?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $preference
     * @return array<int, string>
     */
    private function channelsAllowedByPreference(array $preference, ?string $category): array
    {
        if (filled($preference['opted_out_at'] ?? null)) {
            return [];
        }

        $categoryChannels = $preference['category_channels'] ?? null;
        if (is_string($category) && is_array($categoryChannels) && isset($categoryChannels[$category])) {
            return collect($categoryChannels[$category])->intersect(self::CHANNELS)->values()->all();
        }

        return collect($preference['channels'] ?? self::CHANNELS)->intersect(self::CHANNELS)->values()->all();
    }

    /**
     * @param  array<int, string>  $channels
     * @param  array<int, string>|null  $categories
     * @return array<string, array<int, string>>
     */
    private function defaultCategoryChannels(array $channels, ?array $categories = null): array
    {
        $validChannels = collect($channels)->intersect(self::CHANNELS)->values()->all() ?: ['in_app'];

        return collect($categories ?: self::CATEGORIES)
            ->intersect(self::CATEGORIES)
            ->mapWithKeys(fn (string $category): array => [$category => $validChannels])
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $submitted
     * @param  array<int, string>  $categories
     * @param  array<int, string>  $fallbackChannels
     * @return array<string, array<int, string>>
     */
    private function normalizeCategoryChannels(?array $submitted, array $categories, array $fallbackChannels): array
    {
        if (! is_array($submitted) || $submitted === []) {
            return $this->defaultCategoryChannels($fallbackChannels, $categories);
        }

        $map = [];
        foreach (self::CATEGORIES as $category) {
            $channels = collect($submitted[$category] ?? [])
                ->intersect(self::CHANNELS)
                ->values()
                ->all();

            if ($channels !== []) {
                $map[$category] = $channels;
            }
        }

        return $map === [] ? $this->defaultCategoryChannels($fallbackChannels, $categories) : $map;
    }

    /**
     * @param  array<string, array<int, string>>  $categoryChannels
     * @return array<int, string>
     */
    private function channelsFromCategoryMap(array $categoryChannels): array
    {
        return collect($categoryChannels)->flatten()->intersect(self::CHANNELS)->unique()->values()->all() ?: ['in_app'];
    }

    private function stats(Request $request): array
    {
        $deliveries = $this->deliveries($request);
        $sent = (clone $deliveries)->count();
        $delivered = (clone $deliveries)->where('status', 'delivered')->count();
        $failed = (clone $deliveries)->where('status', 'failed')->count();

        return [
            'sent' => $sent,
            'delivery_rate' => $sent > 0 ? round(($delivered / $sent) * 100, 2) : 0,
            'scheduled' => $this->campaigns($request)->where('send_mode', 'scheduled')->count(),
            'queued' => (clone $deliveries)->where('status', 'queued')->count(),
            'failed' => $failed,
            'templates' => $this->templatesQuery($request)->where('status', 'active')->count(),
            'campaigns' => $this->campaigns($request)->whereDate('created_at', '>=', now()->subDays(30))->count(),
            'integrations' => $this->providerSettings($request)->where('enabled', true)->count(),
        ];
    }

    private function notificationStats(Request $request): array
    {
        return [
            'unread' => $this->deliveries($request)->whereNull('read_at')->where('channel', 'in_app')->count(),
            'action_required' => $this->deliveries($request)->where('status', 'failed')->count(),
            'scheduled_today' => $this->campaigns($request)->whereDate('scheduled_at', today())->count(),
            'sent_today' => $this->deliveries($request)->whereDate('created_at', today())->count(),
            'failed_today' => $this->deliveries($request)->whereDate('created_at', today())->where('status', 'failed')->count(),
            'archived' => $this->deliveries($request)->whereNotNull('read_at')->count(),
        ];
    }

    private function templateStats(Request $request): array
    {
        $base = $this->templatesQuery($request);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'pending' => (clone $base)->where('approval_state', 'pending')->count(),
            'localized' => (clone $base)->where('language', '!=', 'en')->count(),
            'updated' => (clone $base)->whereDate('updated_at', '>=', now()->subDays(7))->count(),
        ];
    }

    private function campaignStats(Request $request): array
    {
        $campaigns = $this->campaigns($request);
        $deliveries = $this->deliveries($request);
        $sent = (clone $deliveries)->count();
        $delivered = (clone $deliveries)->where('status', 'delivered')->count();

        return [
            'active' => (clone $campaigns)->whereIn('status', ['queued', 'partial', 'active'])->count(),
            'scheduled' => (clone $campaigns)->where('send_mode', 'scheduled')->count(),
            'recipients' => (clone $campaigns)->sum('recipient_count'),
            'delivery_rate' => $sent > 0 ? round(($delivered / $sent) * 100, 2) : 0,
            'responses' => (clone $deliveries)->whereNotNull('opened_at')->count(),
            'suppressed' => $this->preferencesQuery($request)->whereNotNull('opted_out_at')->count(),
        ];
    }

    private function scheduledStats(Request $request): array
    {
        return [
            'today' => $this->campaigns($request)->whereDate('scheduled_at', today())->count(),
            'week' => $this->campaigns($request)->whereBetween('scheduled_at', [now(), now()->addWeek()])->count(),
            'rules' => count(self::TRIGGERS),
            'paused' => $this->campaigns($request)->where('status', 'paused')->count(),
            'due' => $this->campaigns($request)->whereBetween('scheduled_at', [now(), now()->addDay()])->count(),
            'queue' => $this->deliveries($request)->where('status', 'queued')->count(),
        ];
    }

    private function deliveryStats(Request $request): array
    {
        $base = $this->deliveries($request);
        $total = (clone $base)->count();
        $delivered = (clone $base)->where('status', 'delivered')->count();
        $failed = (clone $base)->where('status', 'failed')->count();

        return [
            'total' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'retry_queue' => (clone $base)->where('retry_status', 'queued')->count(),
            'avg_latency' => round((clone $base)->whereNotNull('latency_ms')->avg('latency_ms') ?? 0),
            'webhooks' => (clone $base)->whereNotNull('provider_message_id')->count(),
            'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
        ];
    }

    private function preferenceStats(Request $request): array
    {
        $this->syncMemberPreferences($request);
        $base = $this->preferencesQuery($request);
        $total = max((clone $base)->count(), 1);

        return [
            'custom' => (clone $base)->count(),
            'coverage' => $this->members($request)->count(),
            'opted_out' => (clone $base)->whereNotNull('opted_out_at')->count(),
            'push' => (clone $base)->whereJsonContains('channels', 'push')->count(),
            'quiet' => (clone $base)->whereNotNull('quiet_hours_start')->count(),
            'digest' => (clone $base)->where('digest_mode', '!=', 'instant')->count(),
            'opted_out_rate' => round(((clone $base)->whereNotNull('opted_out_at')->count() / $total) * 100, 1),
        ];
    }

    private function integrationStats(Request $request): array
    {
        $settings = $this->providerSettings($request);

        return [
            'connected' => $settings->where('enabled', true)->count(),
            'providers' => $settings->count(),
            'healthy' => $settings->where('last_test_status', 'success')->count(),
            'templates' => $this->templatesQuery($request)->count(),
            'webhooks' => $settings->whereNotNull('webhook_secret_hash')->count(),
            'failures' => $this->deliveries($request)->whereDate('created_at', today())->where('status', 'failed')->count(),
        ];
    }

    private function channelMix(Request $request): array
    {
        return collect(self::CHANNELS)->map(function (string $channel) use ($request): array {
            return ['label' => $this->channelMeta()[$channel]['label'], 'value' => $this->deliveries($request)->where('channel', $channel)->count(), 'color' => $this->channelMeta()[$channel]['color']];
        })->all();
    }

    private function preferenceChannelMix(Request $request): array
    {
        return collect(self::CHANNELS)->map(function (string $channel) use ($request): array {
            return ['label' => $this->channelMeta()[$channel]['label'], 'value' => $this->preferencesQuery($request)->whereJsonContains('channels', $channel)->count(), 'color' => $this->channelMeta()[$channel]['color']];
        })->all();
    }

    private function preferenceOptOutTrend(Request $request): array
    {
        $labels = collect(range(29, 0))->map(fn (int $days): string => now()->subDays($days)->format('M j'))->all();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Opted Out',
                    'color' => '#6d4aff',
                    'values' => collect(range(29, 0))->map(fn (int $days): int => $this->preferencesQuery($request)->whereDate('opted_out_at', '<=', now()->subDays($days)->toDateString())->count())->all(),
                ],
            ],
        ];
    }

    private function preferenceActivity(Request $request): Collection
    {
        return ActivityLog::query()
            ->where('church_id', $this->churchId($request))
            ->where('module', 'Communications')
            ->whereIn('action', ['preference_updated', 'preference_defaults_applied', 'preference_reminder_queued', 'preferences_imported'])
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();
    }

    private function deliveryTrend(Request $request): array
    {
        return collect(range(29, 0))->map(fn (int $days): int => $this->deliveries($request)->whereDate('created_at', now()->subDays($days)->toDateString())->count())->all();
    }

    private function deliveryTrendSeries(Request $request): array
    {
        $labels = collect(range(29, 0))->map(fn (int $days): string => now()->subDays($days)->format('M j'))->all();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Sent',
                    'color' => '#6d4aff',
                    'values' => collect(range(29, 0))->map(fn (int $days): int => $this->deliveries($request)->whereDate('created_at', now()->subDays($days)->toDateString())->count())->all(),
                ],
                [
                    'label' => 'Delivered',
                    'color' => '#10b981',
                    'values' => collect(range(29, 0))->map(fn (int $days): int => $this->deliveries($request)->whereDate('created_at', now()->subDays($days)->toDateString())->where('status', 'delivered')->count())->all(),
                ],
                [
                    'label' => 'Failed',
                    'color' => '#f43f5e',
                    'values' => collect(range(29, 0))->map(fn (int $days): int => $this->deliveries($request)->whereDate('created_at', now()->subDays($days)->toDateString())->where('status', 'failed')->count())->all(),
                ],
            ],
        ];
    }

    private function triggerQueue(Request $request): array
    {
        return collect(self::TRIGGERS)->map(fn (string $trigger): array => [
            'name' => $trigger,
            'queued' => $this->deliveries($request)->where('event_type', $trigger)->count(),
            'template' => $this->templatesQuery($request)->where('trigger_event', $trigger)->exists(),
        ])->all();
    }

    private function queuedListeners(Request $request): array
    {
        return collect([
            ['listener' => 'SendEventNotification', 'events' => ['EventSessionCreated', 'EventSessionUpdated', 'RegistrationConfirmed']],
            ['listener' => 'SendAttendanceConfirmation', 'events' => ['AttendanceSessionOpened', 'AttendanceRecorded']],
            ['listener' => 'SendVolunteerAssignment', 'events' => ['VolunteerAssigned']],
            ['listener' => 'SendCancellationNotice', 'events' => ['EventSessionCancelled']],
        ])->map(function (array $row) use ($request): array {
            $throughput = $this->deliveries($request)->whereIn('event_type', $row['events'])->where('status', 'delivered')->count();
            $failed = $this->deliveries($request)->whereIn('event_type', $row['events'])->where('status', 'failed')->count();

            return [
                'listener' => $row['listener'],
                'status' => $failed > 6 ? 'Warning' : 'Healthy',
                'throughput' => round(max($throughput, 1) / 4.8, 1),
            ];
        })->all();
    }

    private function automationRules(Request $request): array
    {
        return collect(self::TRIGGERS)->map(fn (string $trigger): array => [
            'event' => $trigger,
            'listener' => match ($trigger) {
                'AttendanceRecorded' => 'SendAttendanceConfirmation',
                'EventSessionCancelled' => 'SendCancellationNotice',
                'VolunteerAssigned' => 'SendVolunteerAssignment',
                default => 'SendEventNotification',
            },
            'templates' => $this->templatesQuery($request)->where('trigger_event', $trigger)->count(),
            'next_run' => now()->addMinutes(random_int(15, 360)),
        ])->all();
    }

    private function priorityBreakdown(Request $request): array
    {
        $failed = $this->deliveries($request)->where('status', 'failed')->count();
        $queued = $this->deliveries($request)->where('status', 'queued')->count();
        $delivered = $this->deliveries($request)->where('status', 'delivered')->count();

        return [
            ['label' => 'High', 'value' => $failed, 'color' => '#f43f5e'],
            ['label' => 'Medium', 'value' => $queued, 'color' => '#f97316'],
            ['label' => 'Low', 'value' => $delivered, 'color' => '#10b981'],
        ];
    }

    private function statusBreakdown(Request $request): array
    {
        return collect(['delivered' => '#10b981', 'failed' => '#f43f5e', 'queued' => '#f59e0b'])->map(fn (string $color, string $status): array => [
            'label' => Str::headline($status),
            'value' => $this->deliveries($request)->where('status', $status)->count(),
            'color' => $color,
        ])->values()->all();
    }

    private function notificationTimeline(Request $request): array
    {
        $labels = collect(range(6, 0))->map(fn (int $days): string => now()->subDays($days)->format('M j'))->all();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Sent',
                    'color' => '#6d4aff',
                    'values' => collect(range(6, 0))->map(fn (int $days): int => $this->deliveries($request)->whereDate('created_at', now()->subDays($days)->toDateString())->count())->all(),
                ],
                [
                    'label' => 'Delivered',
                    'color' => '#2477f2',
                    'values' => collect(range(6, 0))->map(fn (int $days): int => $this->deliveries($request)->whereDate('created_at', now()->subDays($days)->toDateString())->where('status', 'delivered')->count())->all(),
                ],
                [
                    'label' => 'Failed',
                    'color' => '#f43f5e',
                    'values' => collect(range(6, 0))->map(fn (int $days): int => $this->deliveries($request)->whereDate('created_at', now()->subDays($days)->toDateString())->where('status', 'failed')->count())->all(),
                ],
                [
                    'label' => 'Read',
                    'color' => '#10b981',
                    'values' => collect(range(6, 0))->map(fn (int $days): int => $this->deliveries($request)->whereDate('created_at', now()->subDays($days)->toDateString())->whereNotNull('read_at')->count())->all(),
                ],
            ],
        ];
    }

    private function templateStatusBreakdown(Request $request): array
    {
        return collect(['active' => '#10b981', 'draft' => '#f59e0b', 'inactive' => '#64748b'])->map(fn (string $color, string $status): array => [
            'label' => Str::headline($status),
            'value' => $this->templatesQuery($request)->where('status', $status)->count(),
            'color' => $color,
        ])->values()->all();
    }

    private function templateUsageTrend(Request $request): array
    {
        $labels = collect(range(29, 0))->map(fn (int $days): string => now()->subDays($days)->format('M j'))->all();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Sends',
                    'color' => '#6d4aff',
                    'values' => collect(range(29, 0))->map(fn (int $days): int => $this->deliveries($request)->whereNotNull('communication_template_id')->whereDate('created_at', now()->subDays($days)->toDateString())->count())->all(),
                ],
                [
                    'label' => 'Delivered',
                    'color' => '#10b981',
                    'values' => collect(range(29, 0))->map(fn (int $days): int => $this->deliveries($request)->whereNotNull('communication_template_id')->whereDate('created_at', now()->subDays($days)->toDateString())->where('status', 'delivered')->count())->all(),
                ],
            ],
        ];
    }

    private function failedReasons(Request $request): array
    {
        return $this->deliveries($request)->where('status', 'failed')->selectRaw('COALESCE(error, ?) as reason, COUNT(*) as total', ['Unknown'])->groupBy('reason')->orderByDesc('total')->limit(6)->get()->all();
    }

    private function providerHealth(Request $request): array
    {
        return $this->providerSettings($request)->map(function (CommunicationProviderSetting $setting) use ($request): array {
            $total = $this->deliveries($request)->where('channel', $setting->channel)->count();
            $failed = $this->deliveries($request)->where('channel', $setting->channel)->where('status', 'failed')->count();

            return [
                'channel' => $setting->channel,
                'provider' => $setting->provider,
                'enabled' => $setting->enabled,
                'rate' => $total > 0 ? round((($total - $failed) / $total) * 100, 2) : ($setting->enabled ? 100 : 0),
            ];
        })->all();
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private function providerCatalog(): array
    {
        return [
            'in_app' => [
                ['label' => 'System Channel', 'value' => 'System Channel'],
            ],
            'email' => [
                ['label' => 'SendGrid', 'value' => 'SendGrid'],
                ['label' => 'SMTP / Mailer', 'value' => 'SMTP / Mailer'],
                ['label' => 'Mailgun', 'value' => 'Mailgun'],
            ],
            'sms' => [
                ['label' => 'Zender SMS Gateway', 'value' => 'Zender SMS Gateway'],
                ['label' => 'Twilio', 'value' => 'Twilio'],
                ['label' => 'Custom SMS Gateway', 'value' => 'Custom SMS Gateway'],
            ],
            'whatsapp' => [
                ['label' => 'Meta WhatsApp', 'value' => 'Meta WhatsApp'],
                ['label' => 'Zender WhatsApp Gateway', 'value' => 'Zender WhatsApp Gateway'],
                ['label' => 'Twilio WhatsApp', 'value' => 'Twilio WhatsApp'],
            ],
            'push' => [
                ['label' => 'Firebase Cloud Messaging', 'value' => 'Firebase Cloud Messaging'],
                ['label' => 'Web Push', 'value' => 'Web Push'],
            ],
        ];
    }

    private function providerConfigurationError(CommunicationProviderSetting $setting): ?string
    {
        if (! $setting->enabled) {
            return 'Channel is disabled.';
        }

        $settings = $setting->settings ?? [];
        $provider = Str::lower($setting->provider);

        if (Str::contains($provider, 'zender')) {
            if (blank($settings['endpoint_url'] ?? null)) {
                return 'Zender site URL is required.';
            }
            if (blank($settings['api_key_encrypted'] ?? null)) {
                return 'Zender API key is required.';
            }
            if ($setting->channel === 'whatsapp' && blank($settings['account_id'] ?? null)) {
                return 'Zender WhatsApp requires a WhatsApp account ID.';
            }
            if ($setting->channel === 'sms' && blank($settings['device_id'] ?? null) && blank($settings['gateway_id'] ?? null)) {
                return 'Zender SMS requires a device unique ID or gateway unique ID.';
            }
        }

        if ($setting->channel !== 'in_app' && blank($settings['queue'] ?? null)) {
            return 'Queue assignment is required.';
        }

        return null;
    }

    private function queueHealth(Request $request): array
    {
        return $this->providerSettings($request)->map(function (CommunicationProviderSetting $setting) use ($request): array {
            $settings = $setting->settings ?? [];
            $processed = $this->deliveries($request)->where('channel', $setting->channel)->count();
            $failed = $this->deliveries($request)->where('channel', $setting->channel)->where('status', 'failed')->count();

            return [
                'queue' => $settings['queue'] ?? $setting->channel.'_queue',
                'workers' => (int) ($settings['workers'] ?? 4),
                'processed' => $processed,
                'failed' => $failed,
                'latency' => (int) round($this->deliveries($request)->where('channel', $setting->channel)->whereNotNull('latency_ms')->avg('latency_ms') ?? 0),
                'status' => $setting->enabled && $this->providerConfigurationError($setting) === null ? 'Healthy' : 'Review',
            ];
        })->all();
    }

    private function providerFailures(Request $request): array
    {
        $failuresToday = $this->deliveries($request)->whereDate('created_at', today())->where('status', 'failed')->count();
        $lastSevenDays = $this->deliveries($request)->where('created_at', '>=', now()->subDays(7))->where('status', 'failed')->count();
        $lastThirtyDays = $this->deliveries($request)->where('created_at', '>=', now()->subDays(30))->where('status', 'failed')->count();
        $total = max($this->deliveries($request)->where('created_at', '>=', now()->subDays(30))->count(), 1);

        return [
            'today' => $failuresToday,
            'last_7_days' => $lastSevenDays,
            'last_30_days' => $lastThirtyDays,
            'mttr' => $failuresToday > 0 ? '18m' : '0m',
            'failure_rate' => round(($lastThirtyDays / $total) * 100, 2),
        ];
    }

    private function retryPipeline(Request $request): array
    {
        $deliveries = $this->deliveries($request);

        return [
            ['label' => 'Queued', 'value' => (clone $deliveries)->where('retry_status', 'queued')->count(), 'icon' => 'refresh-cw', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'In Progress', 'value' => (clone $deliveries)->where('retry_status', 'processing')->count(), 'icon' => 'play', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Waiting (Backoff)', 'value' => (clone $deliveries)->where('retry_status', 'backoff')->count(), 'icon' => 'rotate-ccw', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Scheduled', 'value' => (clone $deliveries)->where('status', 'queued')->count(), 'icon' => 'calendar-clock', 'tone' => 'bg-slate-50 text-slate-600 ring-slate-100'],
        ];
    }

    private function deliveryHeatmap(Request $request): array
    {
        return collect(range(0, 6))->map(function (int $day) use ($request): array {
            $date = now()->startOfWeek()->addDays($day);

            return [
                'label' => $date->format('D'),
                'hours' => collect(range(0, 11))->map(function (int $slot) use ($request, $date): int {
                    $startHour = $slot * 2;

                    return $this->deliveries($request)
                        ->whereDate('created_at', $date->toDateString())
                        ->whereTime('created_at', '>=', sprintf('%02d:00:00', $startHour))
                        ->whereTime('created_at', '<', sprintf('%02d:00:00', min($startHour + 2, 23)))
                        ->count();
                })->all(),
            ];
        })->all();
    }

    private function deliveryHistorySummary(Request $request): array
    {
        $deliveries = $this->deliveries($request);

        return [
            'notifications' => (clone $deliveries)->count(),
            'unique_recipients' => (clone $deliveries)->distinct('recipient_contact')->count('recipient_contact'),
            'channels_used' => (clone $deliveries)->distinct('channel')->count('channel'),
            'templates_used' => (clone $deliveries)->whereNotNull('communication_template_id')->distinct('communication_template_id')->count('communication_template_id'),
            'batches_sent' => (clone $deliveries)->whereNotNull('communication_campaign_id')->distinct('communication_campaign_id')->count('communication_campaign_id'),
        ];
    }

    private function operationalInsights(Request $request): array
    {
        $deliveries = $this->deliveries($request);
        $retryQueue = (clone $deliveries)->where('retry_status', 'queued')->count();
        $oldestQueued = (clone $deliveries)->where('status', 'queued')->oldest()->value('created_at');
        $avgLatency = round((clone $deliveries)->whereNotNull('latency_ms')->avg('latency_ms') ?? 0);
        $channelMeta = $this->channelMeta();
        $enabledProviders = $this->providerSettings($request)->where('enabled', true)->map(fn (CommunicationProviderSetting $setting): array => [
            'label' => $setting->provider,
            'status' => $setting->last_test_status === 'success' ? 'Operational' : 'Review',
            'icon' => $channelMeta[$setting->channel]['icon'] ?? 'radio',
            'tone' => $channelMeta[$setting->channel]['tone'] ?? 'bg-slate-50 text-slate-600 ring-slate-100',
        ])->values()->all();
        $readiness = (int) round((
            ($this->providerSettings($request)->where('enabled', true)->count() / max(count(self::CHANNELS), 1)) * 55
        ) + (
            ($this->templatesQuery($request)->where('status', 'active')->count() / max($this->templatesQuery($request)->count(), 1)) * 45
        ));

        return [
            'retry_queue' => $retryQueue,
            'oldest_queue' => $oldestQueued ? Carbon::parse($oldestQueued)->diffForHumans(['parts' => 2, 'short' => true]) : 'Clear',
            'avg_send_time' => $avgLatency > 0 ? round($avgLatency / 1000, 2) : 0,
            'retry_trend' => collect(range(11, 0))->map(fn (int $days): int => $this->deliveries($request)->whereDate('created_at', now()->subDays($days)->toDateString())->where('retry_status', 'queued')->count())->all(),
            'latency_trend' => collect(range(11, 0))->map(fn (int $days): int => (int) round($this->deliveries($request)->whereDate('created_at', now()->subDays($days)->toDateString())->avg('latency_ms') ?? 0))->all(),
            'providers' => $enabledProviders,
            'automation_readiness' => min(100, $readiness),
        ];
    }

    private function historySummary(Request $request): array
    {
        $deliveries = $this->deliveries($request);
        $sent = (clone $deliveries)->count();

        return [
            ['label' => 'Sent', 'value' => $sent, 'icon' => 'send', 'tone' => 'bg-blue-50 text-blue-600'],
            ['label' => 'Delivered', 'value' => (clone $deliveries)->where('status', 'delivered')->count(), 'icon' => 'shield-check', 'tone' => 'bg-emerald-50 text-emerald-600'],
            ['label' => 'Failed', 'value' => (clone $deliveries)->where('status', 'failed')->count(), 'icon' => 'triangle-alert', 'tone' => 'bg-rose-50 text-rose-600'],
            ['label' => 'Queued', 'value' => (clone $deliveries)->where('status', 'queued')->count(), 'icon' => 'refresh-cw', 'tone' => 'bg-orange-50 text-orange-600'],
            ['label' => 'Retried', 'value' => (clone $deliveries)->where('attempt', '>', 1)->count(), 'icon' => 'rotate-ccw', 'tone' => 'bg-violet-50 text-violet-600'],
        ];
    }

    private function campaigns(Request $request): Builder
    {
        return CommunicationCampaign::query()
            ->where('church_id', $this->churchId($request))
            ->when(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereNull('campus_id')->orWhere('campus_id', $request->user()->campus_id)));
    }

    private function templatesQuery(Request $request): Builder
    {
        return CommunicationTemplate::query()
            ->where('church_id', $this->churchId($request))
            ->when(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereNull('campus_id')->orWhere('campus_id', $request->user()->campus_id)));
    }

    private function deliveries(Request $request): Builder
    {
        return CommunicationDelivery::query()
            ->where('church_id', $this->churchId($request))
            ->when(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id, fn (Builder $query) => $query->whereHas('member', fn (Builder $memberQuery) => $memberQuery->where('campus_id', $request->user()->campus_id)));
    }

    private function filteredDeliveries(Request $request): Builder
    {
        return $this->deliveries($request)
            ->when(filled($request->query('channel')), fn (Builder $query) => $query->where('channel', $request->query('channel')))
            ->when(filled($request->query('status')), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when(filled($request->query('retry_status')), fn (Builder $query) => $query->where('retry_status', $request->query('retry_status')))
            ->when(filled($request->query('provider')), fn (Builder $query) => $query->where('provider', $request->query('provider')))
            ->when(filled($request->query('template_id')), fn (Builder $query) => $query->where('communication_template_id', $request->query('template_id')))
            ->when(filled($request->query('event_type')), fn (Builder $query) => $query->where('event_type', $request->query('event_type')))
            ->when(filled($request->query('batch')), function (Builder $query) use ($request): void {
                $batch = strtolower((string) $request->query('batch'));
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(provider_message_id) LIKE ?', ['%'.$batch.'%'])
                    ->orWhere('communication_campaign_id', is_numeric($batch) ? (int) $batch : 0));
            })
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = strtolower((string) $request->query('q'));
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(recipient_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(recipient_contact) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(provider_message_id) LIKE ?', ['%'.$search.'%']));
            })
            ->when(filled($request->query('date_from')), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->query('date_from')))
            ->when(filled($request->query('date_to')), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->query('date_to')));
    }

    private function preferencesQuery(Request $request): Builder
    {
        return UserNotificationPreference::query()
            ->where('church_id', $this->churchId($request))
            ->when(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id, fn (Builder $query) => $query->whereHas('member', fn (Builder $memberQuery) => $memberQuery->where('campus_id', $request->user()->campus_id)));
    }

    private function selectedPreference(Request $request, ?UserNotificationPreference $fallback): ?UserNotificationPreference
    {
        $selectedId = OpaqueId::decode((string) $request->query('selected'), UserNotificationPreference::class);

        if (! $selectedId) {
            return $fallback;
        }

        return $this->preferencesQuery($request)
            ->with(['member.campus', 'user.roles', 'user.campus'])
            ->whereKey($selectedId)
            ->first() ?? $fallback;
    }

    private function preferenceSelection(Request $request): Builder
    {
        $ids = OpaqueId::decodeMany($request->input('selected', []), UserNotificationPreference::class);
        $query = $this->preferencesQuery($request);

        return $ids === [] ? $query : $query->whereKey($ids);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function updatePreferencePerson(UserNotificationPreference $preference, array $validated, Request $request): void
    {
        $campusId = filled($validated['campus_id'] ?? null) ? (int) $validated['campus_id'] : null;

        if ($campusId !== null) {
            abort_unless($request->user()?->canAccessCampus($campusId), 403);
        }

        if ($preference->member) {
            $updates = [];

            if (filled($validated['person_name'] ?? null)) {
                $parts = preg_split('/\s+/', trim((string) $validated['person_name']), 2) ?: [];
                $updates['first_name'] = $parts[0] ?? $preference->member->first_name;
                $updates['last_name'] = $parts[1] ?? $preference->member->last_name;
            }

            foreach (['person_email' => 'email', 'person_phone' => 'phone', 'person_status' => 'status'] as $input => $column) {
                if (array_key_exists($input, $validated)) {
                    $updates[$column] = $validated[$input];
                }
            }

            if ($campusId !== null) {
                $updates['campus_id'] = $campusId;
            }

            if ($updates !== []) {
                $preference->member->update($updates);
            }

            return;
        }

        if ($preference->user) {
            $updates = [];

            if (filled($validated['person_name'] ?? null)) {
                $updates['name'] = trim((string) $validated['person_name']);
            }

            if (filled($validated['person_email'] ?? null)) {
                $updates['email'] = $validated['person_email'];
            }

            if ($campusId !== null) {
                $updates['campus_id'] = $campusId;
            }

            if ($updates !== []) {
                $preference->user->update($updates);
            }
        }
    }

    private function audienceQuery(Request $request, array $filters): Builder
    {
        return $this->members($request)
            ->with('memberProfile')
            ->when(filled($filters['campus_id'] ?? null), fn (Builder $query) => $query->where('campus_id', $filters['campus_id']))
            ->when(filled($filters['member_status'] ?? null), fn (Builder $query) => $query->where('status', str_replace('_', ' ', (string) $filters['member_status'])))
            ->when(($filters['audience_role'] ?? null) === 'volunteers', fn (Builder $query) => $query->whereHas('volunteers'))
            ->when(($filters['volunteer_status'] ?? null) === 'active', fn (Builder $query) => $query->whereHas('volunteers', fn (Builder $volunteerQuery) => $volunteerQuery->where('status', 'active')))
            ->when(($filters['volunteer_status'] ?? null) === 'none', fn (Builder $query) => $query->whereDoesntHave('volunteers'))
            ->when(filled($filters['follow_up_need'] ?? null), fn (Builder $query) => $query->whereHas('careTasks', fn (Builder $careQuery) => $careQuery->where('type', str_replace('-', ' ', (string) $filters['follow_up_need']))))
            ->when(in_array(($filters['absentee_window'] ?? null), ['30', '60'], true), function (Builder $query) use ($filters): void {
                $days = (int) $filters['absentee_window'];
                $query->whereDoesntHave('attendanceRecords', fn (Builder $attendanceQuery) => $attendanceQuery->where('service_date', '>=', now()->subDays($days)->toDateString()));
            })
            ->when(($filters['absentee_window'] ?? null) === 'never', fn (Builder $query) => $query->whereDoesntHave('attendanceRecords'));
    }

    private function members(Request $request): Builder
    {
        return Member::query()
            ->where('church_id', $this->churchId($request))
            ->when(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id, fn (Builder $query) => $query->where('campus_id', $request->user()->campus_id));
    }

    private function campuses(Request $request): Collection
    {
        return Campus::query()
            ->where('church_id', $this->churchId($request))
            ->when(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id, fn (Builder $query) => $query->where('id', $request->user()->campus_id))
            ->orderBy('name')
            ->get();
    }

    private function extractVariables(string $content): array
    {
        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $content, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function authorizeCommunications(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage communications'), 403);
    }

    private function authorizeCommunicationIntegrations(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage settings'), 403);
    }

    private function authorizeCommunicationRecord(Request $request, mixed $record): void
    {
        $this->authorizeCommunications($request);
        $campusId = $record->campus_id ?? $record->member?->campus_id ?? $record->user?->campus_id ?? null;

        abort_unless($request->user()?->canAccessChurch($record->church_id ?? null), 403);
        abort_unless($request->user()?->canAccessCampus($campusId), 403);
    }

    private function churchId(Request $request): int
    {
        return (int) ($request->user()?->church_id ?? Church::query()->value('id'));
    }

    private function breadcrumbs(string $label): array
    {
        return [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Communications', 'url' => route('communications.index')],
            ['label' => $label, 'url' => null],
        ];
    }

    private function administrationBreadcrumbs(string $label): array
    {
        return [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Administration', 'url' => route('users.index')],
            ['label' => $label, 'url' => null],
        ];
    }
}
