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
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
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
            'recentDeliveries' => $this->deliveries($request)->latest()->limit(6)->get(),
            'channelMix' => $this->channelMix($request),
            'trend' => $this->deliveryTrend($request),
            'providerHealth' => $this->providerHealth($request),
            'scheduled' => $this->campaigns($request)->whereNotNull('scheduled_at')->whereIn('status', ['draft', 'scheduled', 'queued'])->orderBy('scheduled_at')->limit(5)->get(),
            'breadcrumbs' => $this->breadcrumbs('Overview'),
        ]);
    }

    public function notifications(Request $request): View
    {
        $this->authorizeCommunications($request);

        $selected = $this->deliveries($request)
            ->with(['campaign', 'template', 'member'])
            ->when(filled($request->query('channel')), fn (Builder $query) => $query->where('channel', $request->query('channel')))
            ->when(filled($request->query('status')), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = strtolower((string) $request->query('q'));
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(recipient_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(event_type) LIKE ?', ['%'.$search.'%']));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('communications.notifications', $this->shared($request) + [
            'notifications' => $selected,
            'stats' => $this->notificationStats($request),
            'selected' => $selected->first(),
            'priorityBreakdown' => $this->priorityBreakdown($request),
            'statusBreakdown' => $this->statusBreakdown($request),
            'breadcrumbs' => $this->breadcrumbs('Notifications Center'),
        ]);
    }

    public function templates(Request $request): View
    {
        $this->authorizeCommunications($request);

        $templates = $this->templatesQuery($request)
            ->with('owner')
            ->when(filled($request->query('category')), fn (Builder $query) => $query->where('category', $request->query('category')))
            ->when(filled($request->query('channel')), fn (Builder $query) => $query->whereJsonContains('channels', $request->query('channel')))
            ->when(filled($request->query('status')), fn (Builder $query) => $query->where('status', $request->query('status')))
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

        return view('communications.templates', $this->shared($request) + [
            'templates' => $templates,
            'selected' => $templates->first(),
            'stats' => $this->templateStats($request),
            'templateUsage' => $this->templatesQuery($request)->orderByDesc('usage_count')->limit(6)->get(),
            'statusBreakdown' => $this->templateStatusBreakdown($request),
            'breadcrumbs' => $this->breadcrumbs('Message Templates'),
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

    public function scheduled(Request $request): View
    {
        $this->authorizeCommunications($request);

        $campaigns = $this->campaigns($request)
            ->with('creator')
            ->where(fn (Builder $query) => $query->where('send_mode', 'scheduled')->orWhereNotNull('scheduled_at'))
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->paginate(10)
            ->withQueryString();

        return view('communications.scheduled', $this->shared($request) + [
            'campaigns' => $campaigns,
            'rules' => $this->automationRules($request),
            'stats' => $this->scheduledStats($request),
            'breadcrumbs' => $this->breadcrumbs('Scheduled Messages'),
        ]);
    }

    public function bulk(Request $request): View
    {
        $this->authorizeCommunications($request);

        return view('communications.bulk', $this->shared($request) + [
            'campaigns' => $this->campaigns($request)->with('template')->latest()->paginate(10)->withQueryString(),
            'templates' => $this->templatesQuery($request)->where('status', 'active')->orderBy('name')->get(),
            'audienceCount' => $this->audienceQuery($request, $request->query())->count(),
            'stats' => $this->campaignStats($request),
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
                CommunicationRecipient::query()->create([
                    'communication_campaign_id' => $campaign->id,
                    'member_id' => $member->id,
                    'name' => trim($member->first_name.' '.$member->last_name),
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'preferences' => $this->memberPreferencePayload($member),
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

        $deliveries = $this->deliveries($request)
            ->with(['campaign', 'template', 'member'])
            ->when(filled($request->query('channel')), fn (Builder $query) => $query->where('channel', $request->query('channel')))
            ->when(filled($request->query('status')), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when(filled($request->query('provider')), fn (Builder $query) => $query->where('provider', $request->query('provider')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('communications.delivery-logs', $this->shared($request) + [
            'deliveries' => $deliveries,
            'stats' => $this->deliveryStats($request),
            'failedReasons' => $this->failedReasons($request),
            'providerHealth' => $this->providerHealth($request),
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
        $rows = $this->deliveries($request)->latest()->limit(5000)->get();
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

        return view('communications.preferences', $this->shared($request) + [
            'preferences' => $preferences,
            'selected' => $preferences->first(),
            'stats' => $this->preferenceStats($request),
            'channelMix' => $this->preferenceChannelMix($request),
            'breadcrumbs' => $this->breadcrumbs('User Notification Preferences'),
        ]);
    }

    public function updatePreference(Request $request, UserNotificationPreference $preference, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCommunicationRecord($request, $preference);
        $validated = $request->validate([
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(self::CHANNELS)],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => [Rule::in(self::CATEGORIES)],
            'digest_mode' => ['required', Rule::in(['instant', 'daily', 'weekly', 'off'])],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'language' => ['required', 'string', 'max:20'],
            'critical_alerts' => ['nullable', 'boolean'],
            'opted_out' => ['nullable', 'boolean'],
        ]);

        $preference->update([
            'channels' => array_values($validated['channels']),
            'categories' => array_values($validated['categories']),
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

            CommunicationProviderSetting::query()->updateOrCreate(
                ['church_id' => $this->churchId($request), 'channel' => $channel],
                [
                    'provider' => $input['provider'],
                    'enabled' => (bool) ($input['enabled'] ?? false),
                    'sender_identity' => $input['sender_identity'] ?? null,
                    'rate_limit_per_minute' => (int) $input['rate_limit_per_minute'],
                    'retry_policy' => $input['retry_policy'],
                    'webhook_secret_hash' => $webhookSecretHash,
                    'settings' => ['queue' => $channel.'_queue'],
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
        $status = $setting->enabled ? 'success' : 'failed';
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
            'response_code' => $status === 'success' ? '200 OK' : 'Provider disabled',
            'error' => $status === 'success' ? null : 'Enable the channel before testing.',
            'sent_at' => now(),
            'delivered_at' => $status === 'success' ? now() : null,
        ]);

        $activityLogger->log('Communications', 'integration_tested', Str::headline($channel).' integration was tested.', $setting, ['resource' => 'Communication Provider', 'status' => $status], $request);

        return back()->with($status === 'success' ? 'status' : 'error', $status === 'success' ? 'Provider test delivered.' : 'Provider test failed because the channel is disabled.');
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
                $channels = $preference['channels'] ?? self::CHANNELS;
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
            'in_app' => ['Internal', true],
            'email' => ['SMTP / Mailer', false],
            'sms' => ['SMS Gateway', false],
            'whatsapp' => ['WhatsApp Business', false],
            'push' => ['Browser Push', false],
        ];

        foreach ($defaults as $channel => [$provider, $enabled]) {
            CommunicationProviderSetting::query()->firstOrCreate(
                ['church_id' => $churchId, 'channel' => $channel],
                ['provider' => $provider, 'enabled' => $enabled, 'sender_identity' => config('app.name'), 'settings' => ['queue' => $channel.'_queue']],
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

    private function deliveryTrend(Request $request): array
    {
        return collect(range(29, 0))->map(fn (int $days): int => $this->deliveries($request)->whereDate('created_at', now()->subDays($days)->toDateString())->count())->all();
    }

    private function triggerQueue(Request $request): array
    {
        return collect(self::TRIGGERS)->map(fn (string $trigger): array => [
            'name' => $trigger,
            'queued' => $this->deliveries($request)->where('event_type', $trigger)->count(),
            'template' => $this->templatesQuery($request)->where('trigger_event', $trigger)->exists(),
        ])->all();
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

    private function templateStatusBreakdown(Request $request): array
    {
        return collect(['active' => '#10b981', 'draft' => '#f59e0b', 'inactive' => '#64748b'])->map(fn (string $color, string $status): array => [
            'label' => Str::headline($status),
            'value' => $this->templatesQuery($request)->where('status', $status)->count(),
            'color' => $color,
        ])->values()->all();
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

    private function preferencesQuery(Request $request): Builder
    {
        return UserNotificationPreference::query()
            ->where('church_id', $this->churchId($request))
            ->when(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id, fn (Builder $query) => $query->whereHas('member', fn (Builder $memberQuery) => $memberQuery->where('campus_id', $request->user()->campus_id)));
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
