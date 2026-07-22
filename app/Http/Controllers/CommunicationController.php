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
use App\Models\EventSession;
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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        ]);

        $filters = [
            'campus_id' => $validated['campus_id'] ?? null,
            'member_status' => $validated['member_status'] ?? null,
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
        $this->authorizeCommunications($request);

        return view('communications.integrations', $this->shared($request) + [
            'settings' => $this->providerSettings($request),
            'stats' => $this->integrationStats($request),
            'providerHealth' => $this->providerHealth($request),
            'providerCatalog' => $this->providerCatalog(),
            'queueHealth' => $this->queueHealth($request),
            'providerFailures' => $this->providerFailures($request),
            'breadcrumbs' => $this->breadcrumbs('Channel Integrations & Communication Settings'),
        ]);
    }

    public function updateIntegrations(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunications($request);
        $validated = $request->validate([
            'providers' => ['required', 'array'],
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
            'providers.*.sender_number' => ['nullable', 'string', 'max:80'],
            'providers.*.webhook_url' => ['nullable', 'url', 'max:255'],
            'providers.*.queue' => ['nullable', 'string', 'max:80'],
            'providers.*.workers' => ['nullable', 'integer', 'min:1', 'max:100'],
            'providers.*.daily_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'providers.*.region' => ['nullable', 'string', 'max:120'],
        ]);

        foreach (self::CHANNELS as $channel) {
            $input = $validated['providers'][$channel] ?? [];
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
                'webhook_url' => $input['webhook_url'] ?? null,
                'queue' => $input['queue'] ?? $channel.'_queue',
                'workers' => (int) ($input['workers'] ?? ($settings['workers'] ?? 4)),
                'daily_limit' => (int) ($input['daily_limit'] ?? ($settings['daily_limit'] ?? 100000)),
                'region' => $input['region'] ?? ($settings['region'] ?? 'US Central'),
                'provider_url' => Str::contains(Str::lower((string) $input['provider']), 'zender') ? 'https://codecanyon.net/item/zender-android-mobile-devices-as-sms-gateway-saas-platform/26594230' : ($settings['provider_url'] ?? null),
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

        $activityLogger->log('Communications', 'integrations_updated', 'Communication channel integrations were updated.', null, ['resource' => 'Communication Settings', 'status' => 'success'], $request);

        return back()->with('status', 'Communication integrations saved.');
    }

    public function testIntegration(Request $request, string $channel, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunications($request);
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
                $enabled = $channel === 'in_app' || (bool) $setting?->enabled;
                $status = $enabled ? 'delivered' : 'failed';
                $sent++;
                $delivered += $status === 'delivered' ? 1 : 0;
                $failed += $status === 'failed' ? 1 : 0;

                CommunicationDelivery::query()->create([
                    'church_id' => $campaign->church_id,
                    'communication_campaign_id' => $campaign->id,
                    'communication_template_id' => $campaign->template_id,
                    'member_id' => $recipient->member_id,
                    'channel' => $channel,
                    'provider' => $setting?->provider ?? ($channel === 'in_app' ? 'Internal' : Str::headline($channel)),
                    'recipient_name' => $recipient->name,
                    'recipient_contact' => $this->recipientContact($recipient, $channel),
                    'subject' => $campaign->subject,
                    'body_excerpt' => Str::limit(strip_tags($campaign->body), 180),
                    'event_type' => $campaign->template?->trigger_event ?? 'BulkCampaign',
                    'status' => $status,
                    'retry_status' => $status === 'failed' ? 'queued' : 'none',
                    'attempt' => 1,
                    'latency_ms' => $status === 'delivered' ? random_int(90, 900) : null,
                    'provider_message_id' => strtoupper($channel).'-'.Str::upper(Str::random(10)),
                    'response_code' => $status === 'delivered' ? '200 OK' : 'Provider disabled',
                    'error' => $status === 'failed' ? 'Channel is not enabled in communication integrations.' : null,
                    'sent_at' => now(),
                    'delivered_at' => $status === 'delivered' ? now() : null,
                    'read_at' => $channel === 'in_app' && $status === 'delivered' ? null : null,
                ]);
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

    private function providerSettings(Request $request): \Illuminate\Support\Collection
    {
        $churchId = $this->churchId($request);
        $defaults = [
            'in_app' => ['System Channel', true],
            'email' => ['SendGrid', false],
            'sms' => ['Zender SMS Gateway', false],
            'whatsapp' => ['Meta WhatsApp', false],
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
                        'provider_url' => $channel === 'sms' ? 'https://codecanyon.net/item/zender-android-mobile-devices-as-sms-gateway-saas-platform/26594230' : null,
                    ],
                ],
            );
        }

        return CommunicationProviderSetting::query()->where('church_id', $churchId)->orderByRaw("case channel when 'in_app' then 1 when 'email' then 2 when 'sms' then 3 when 'whatsapp' then 4 else 5 end")->get();
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
     * @param array<string, mixed> $preference
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
     * @param array<int, string> $channels
     * @param array<int, string>|null $categories
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
     * @param array<string, mixed>|null $submitted
     * @param array<int, string> $categories
     * @param array<int, string> $fallbackChannels
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
     * @param array<string, array<int, string>> $categoryChannels
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

    private function preferenceActivity(Request $request): \Illuminate\Support\Collection
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
                return 'Zender endpoint URL is required.';
            }
            if (blank($settings['api_key_encrypted'] ?? null)) {
                return 'Zender API token is required.';
            }
            if ($setting->channel === 'sms' && blank($settings['device_id'] ?? null) && blank($settings['sender_number'] ?? null)) {
                return 'Zender SMS requires a device ID or sender number.';
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
     * @param array<string, mixed> $validated
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
            ->when(filled($filters['member_status'] ?? null), fn (Builder $query) => $query->where('status', $filters['member_status']));
    }

    private function members(Request $request): Builder
    {
        return Member::query()
            ->where('church_id', $this->churchId($request))
            ->when(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id, fn (Builder $query) => $query->where('campus_id', $request->user()->campus_id));
    }

    private function campuses(Request $request): \Illuminate\Support\Collection
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
}
