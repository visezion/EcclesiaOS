<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CentralSupportSession;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketNotification;
use App\Services\ActivityLogger;
use App\Services\CentralSupportSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class RemoteSupportAccessController extends Controller
{
    private const STATUSES = ['new', 'triaged', 'in_progress', 'waiting_on_church', 'resolved', 'closed'];

    public function exchange(Request $request, CentralSupportSettings $settings): JsonResponse
    {
        $validated = $request->validate([
            'grant_token' => ['required', 'string', 'size:64'],
            'agent_id' => ['required', 'string', 'max:255'],
            'agent_name' => ['required', 'string', 'max:255'],
            'agent_email' => ['required', 'email', 'max:255'],
        ]);
        $session = CentralSupportSession::query()
            ->with('church')
            ->where('grant_token_hash', hash('sha256', $validated['grant_token']))
            ->firstOrFail();
        abort_unless($session->status === 'pending' && $session->isUsable(), 410, 'This support grant is expired, used, or revoked.');
        $connection = $settings->forChurch($session->church);
        abort_unless($connection['enabled'] && $connection['remote_access_enabled'], 403, 'Remote support is disabled.');
        abort_unless(
            filled($connection['api_token'])
            && filled($request->bearerToken())
            && hash_equals((string) $connection['api_token'], (string) $request->bearerToken()),
            401,
            'The central support server is not authorized.',
        );
        $loginToken = Str::random(64);
        $session->update([
            'login_token_hash' => hash('sha256', $loginToken),
            'central_agent_id' => $validated['agent_id'],
            'agent_name' => $validated['agent_name'],
            'agent_email' => $validated['agent_email'],
            'status' => 'ready',
            'exchanged_at' => now(),
        ]);

        return response()->json([
            'login_url' => route('central-support.remote.login', ['token' => $loginToken]),
            'expires_at' => $session->expires_at->toIso8601String(),
            'church' => $session->church->name,
        ]);
    }

    public function login(Request $request, string $token, ActivityLogger $logger): RedirectResponse
    {
        abort_unless(strlen($token) === 64, 404);
        $session = CentralSupportSession::query()
            ->with('church')
            ->where('login_token_hash', hash('sha256', $token))
            ->firstOrFail();
        abort_unless($session->status === 'ready' && $session->isUsable(), 410, 'This remote support login is expired, used, or revoked.');

        $user = DB::transaction(function () use ($session): User {
            $user = User::query()->create([
                'church_id' => $session->church_id,
                'name' => 'Central Support · '.$session->agent_name,
                'email' => 'remote-support-'.$session->id.'@ecclesiaos.invalid',
                'password' => Hash::make(Str::random(64)),
                'title' => 'Temporary Developer Support',
                'status' => 'active',
                'email_verified_at' => now(),
                'account_settings' => [
                    'remote_support' => [
                        'managed' => true,
                        'session_id' => $session->id,
                        'central_agent_id' => $session->central_agent_id,
                    ],
                ],
            ]);
            $role = Role::query()->where('name', 'Super Administrator')->firstOrFail();
            $user->roles()->sync([$role->id]);
            $session->update([
                'support_user_id' => $user->id,
                'status' => 'active',
                'started_at' => now(),
                'last_seen_at' => now(),
                'login_token_hash' => null,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('remote_support_session_id', $session->id);
        $logger->log('Support', 'remote_support_login', 'A central developer support session started.', $session, [
            'central_agent_id' => $session->central_agent_id,
            'agent_email' => $session->agent_email,
            'expires_at' => $session->expires_at->toIso8601String(),
            'risk' => 'high',
        ], $request);

        return redirect()->route($session->support_ticket_id ? 'support.tickets.show' : 'dashboard', $session->ticket ? [$session->ticket] : []);
    }

    public function event(Request $request, CentralSupportSettings $settings): JsonResponse
    {
        $installationId = (string) $request->header('X-EcclesiaOS-Installation');
        abort_if($installationId === '', 401, 'Missing installation identity.');
        $setting = Setting::query()
            ->with('church')
            ->where('key', 'central_support.connection')
            ->get()
            ->first(fn (Setting $item): bool => hash_equals((string) data_get($item->value, 'installation_id'), $installationId));
        abort_unless($setting?->church, 401, 'Unknown installation identity.');
        $connection = $settings->forChurch($setting->church);
        abort_unless(
            $connection['enabled']
            && filled($connection['api_token'])
            && filled($request->bearerToken())
            && hash_equals((string) $connection['api_token'], (string) $request->bearerToken()),
            401,
            'The central support server is not authorized.',
        );
        $validated = $request->validate([
            'event_id' => ['required', 'uuid'],
            'event_type' => ['required', Rule::in(['ticket.updated', 'ticket.reply.created'])],
            'ticket_id' => ['nullable', 'uuid'],
            'reference' => ['required', 'string', 'max:40'],
            'payload' => ['required', 'array'],
            'payload.status' => ['nullable', Rule::in(self::STATUSES)],
            'payload.priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'payload.progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'payload.body' => ['nullable', 'string', 'max:10000'],
            'payload.is_internal' => ['nullable', 'boolean'],
            'payload.agent_name' => ['nullable', 'string', 'max:255'],
        ]);
        if (DB::table('central_support_inbound_events')->where('event_id', $validated['event_id'])->exists()) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $ticket = SupportTicket::query()
            ->where('church_id', $setting->church_id)
            ->where(fn ($query) => $query
                ->when(filled($validated['ticket_id'] ?? null), fn ($candidate) => $candidate->orWhere('central_id', $validated['ticket_id']))
                ->orWhere('reference', $validated['reference']))
            ->firstOrFail();

        DB::transaction(function () use ($validated, $ticket, $setting): void {
            $payload = $validated['payload'];
            if ($validated['event_type'] === 'ticket.updated') {
                $updates = collect($payload)->only(['status', 'priority', 'progress'])->all();
                $updates['central_id'] = $ticket->central_id ?: ($validated['ticket_id'] ?? null);
                $updates['sync_status'] = 'synced';
                $updates['sync_error'] = null;
                $updates['synced_at'] = now();
                $updates['last_activity_at'] = now();
                if (in_array($updates['status'] ?? null, ['resolved', 'closed'], true)) {
                    $updates['progress'] = 100;
                    $updates['resolved_at'] = $ticket->resolved_at ?? now();
                }
                if (($updates['status'] ?? null) === 'closed') {
                    $updates['closed_at'] = $ticket->closed_at ?? now();
                }
                $ticket->update($updates);
                $ticket->activities()->create([
                    'type' => 'tracking_updated',
                    'description' => 'Central support updated tracking to '.str($ticket->status)->headline().' at '.$ticket->progress.'%.',
                    'metadata' => ['source' => 'central_support', 'agent_name' => $payload['agent_name'] ?? null],
                ]);
            } else {
                abort_unless(filled($payload['body'] ?? null), 422, 'A reply body is required.');
                $reply = $ticket->replies()->create([
                    'user_id' => null,
                    'body' => $payload['body'],
                    'is_internal' => (bool) ($payload['is_internal'] ?? false),
                ]);
                $ticket->update([
                    'central_id' => $ticket->central_id ?: ($validated['ticket_id'] ?? null),
                    'sync_status' => 'synced',
                    'sync_error' => null,
                    'synced_at' => now(),
                    'last_activity_at' => now(),
                    'first_response_at' => $ticket->first_response_at ?? now(),
                    'status' => $ticket->status === 'new' ? 'triaged' : $ticket->status,
                    'progress' => $ticket->status === 'new' ? max(20, $ticket->progress) : $ticket->progress,
                ]);
                $ticket->activities()->create([
                    'type' => $reply->is_internal ? 'internal_note' : 'reply',
                    'description' => $reply->is_internal ? 'Central support added an internal note.' : 'Central support replied to the ticket.',
                    'metadata' => ['source' => 'central_support', 'agent_name' => $payload['agent_name'] ?? null],
                ]);
            }
            DB::table('central_support_inbound_events')->insert([
                'church_id' => $setting->church_id,
                'event_id' => $validated['event_id'],
                'event_type' => $validated['event_type'],
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        if (! (bool) data_get($validated, 'payload.is_internal') && $ticket->creator) {
            $ticket->creator->notify(new SupportTicketNotification($ticket, 'Central support update', $ticket->reference.' has a new update.'));
        }

        return response()->json(['received' => true]);
    }

    public function end(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $sessionId = $request->session()->get('remote_support_session_id');
        $session = CentralSupportSession::query()->find($sessionId);
        if ($session) {
            $logger->log('Support', 'remote_support_logout', 'The central developer support session ended.', $session, ['risk' => 'high'], $request);
            $session->update(['status' => 'ended', 'revoked_at' => now()]);
            $request->user()?->update(['status' => 'inactive']);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Remote support session ended.');
    }
}
