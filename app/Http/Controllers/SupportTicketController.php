<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Notifications\SupportTicketNotification;
use App\Services\ActivityLogger;
use App\Services\CentralSupportClient;
use App\Services\CentralSupportOutbox;
use App\Services\CentralSupportSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class SupportTicketController extends Controller
{
    private const CATEGORIES = [
        'bug' => 'Bug or error',
        'idea' => 'Idea or suggestion',
        'feature_expansion' => 'Expand an existing function',
        'new_feature' => 'Create a new function',
        'integration' => 'Integration request',
        'performance' => 'Performance or speed',
        'security' => 'Security concern',
        'account' => 'Account or access',
        'data' => 'Data or reporting',
        'billing' => 'Billing or subscription',
        'training' => 'Training request',
        'how_to' => 'How-to question',
        'other' => 'Other',
    ];

    private const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    private const STATUSES = [
        'new' => 'New',
        'triaged' => 'Triaged',
        'in_progress' => 'In progress',
        'waiting_on_church' => 'Waiting on church',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    public function index(Request $request, CentralSupportSettings $centralSettings): View
    {
        $base = $this->visibleTickets($request);
        $recentTickets = $this->visibleTickets($request)
            ->with(['church', 'creator', 'assignee'])
            ->withCount(['replies', 'attachments'])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();
        $church = $request->user()->church;
        $connection = $church ? $centralSettings->forChurch($church) : null;

        return view('support.index', [
            'recentTickets' => $recentTickets,
            'suggestedTickets' => $this->visibleTickets($request)
                ->whereIn('status', ['resolved', 'closed'])
                ->latest('resolved_at')
                ->limit(4)
                ->get(),
            'categories' => self::CATEGORIES,
            'priorities' => self::PRIORITIES,
            'statuses' => self::STATUSES,
            'connection' => $connection,
            'stats' => [
                'open' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->count(),
                'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
                'waiting' => (clone $base)->where('status', 'waiting_on_church')->count(),
                'resolved' => (clone $base)->whereIn('status', ['resolved', 'closed'])->count(),
                'failed' => (clone $base)->where('sync_status', 'failed')->count(),
            ],
            'syncStats' => [
                'synced' => (clone $base)->where('sync_status', 'synced')->count(),
                'pending' => (clone $base)->whereIn('sync_status', ['local', 'pending'])->count(),
                'failed' => (clone $base)->where('sync_status', 'failed')->count(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Support Center', 'url' => null],
            ],
        ]);
    }

    public function tickets(Request $request): View
    {
        $query = $this->visibleTickets($request)->with(['church', 'creator', 'assignee'])->withCount(['replies', 'attachments']);
        $this->applyFilters($query, $request);
        $tickets = $query->orderByDesc('last_activity_at')->orderByDesc('id')->paginate(12)->withQueryString();
        $base = $this->visibleTickets($request);

        return view('support.tickets', [
            'tickets' => $tickets,
            'selectedTicket' => $tickets->first(),
            'categories' => self::CATEGORIES,
            'priorities' => self::PRIORITIES,
            'statuses' => self::STATUSES,
            'stats' => [
                'open' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->count(),
                'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
                'waiting' => (clone $base)->where('status', 'waiting_on_church')->count(),
                'resolved' => (clone $base)->whereIn('status', ['resolved', 'closed'])->count(),
                'failed' => (clone $base)->where('sync_status', 'failed')->count(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Support Center', 'url' => route('support.index')],
                ['label' => 'My Tickets', 'url' => null],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('support.create', [
            'categories' => self::CATEGORIES,
            'priorities' => self::PRIORITIES,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Support Center', 'url' => route('support.index')],
                ['label' => 'New Ticket', 'url' => null],
            ],
        ]);
    }

    public function store(
        Request $request,
        ActivityLogger $activityLogger,
        CentralSupportOutbox $outbox,
        CentralSupportClient $centralClient,
        CentralSupportSettings $centralSettings,
    ): RedirectResponse
    {
        $validated = $request->validate($this->ticketRules());
        $ticket = DB::transaction(function () use ($request, $validated): SupportTicket {
            $ticket = SupportTicket::query()->create([
                ...collect($validated)->except('attachments')->all(),
                'browser' => filled($validated['browser'] ?? null)
                    ? $validated['browser']
                    : Str::limit((string) $request->userAgent(), 255, ''),
                'reference' => $this->newReference(),
                'church_id' => $request->user()->church_id,
                'created_by' => $request->user()->id,
                'status' => 'new',
                'progress' => 5,
                'last_activity_at' => now(),
            ]);
            $ticket->activities()->create([
                'user_id' => $request->user()->id,
                'type' => 'created',
                'description' => 'Ticket submitted and queued for review.',
                'metadata' => ['status' => 'new', 'progress' => 5],
            ]);

            return $ticket;
        });

        try {
            $this->storeAttachments($request->file('attachments', []), $ticket, null, $request->user()->id);
        } catch (Throwable $exception) {
            $this->deleteStoredAttachments($ticket);
            $ticket->delete();
            report($exception);

            abort(503, 'The ticket attachment could not be stored. Please try again.');
        }

        $activityLogger->log('Support', 'support_ticket_created', 'Support ticket '.$ticket->reference.' submitted.', $ticket, [
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => 'success',
            'risk' => $ticket->priority,
        ], $request);
        $outbox->enqueueTicket($ticket->load(['church', 'creator', 'attachments']));
        $connection = $ticket->church ? $centralSettings->forChurch($ticket->church) : null;
        if ($connection && $connection['enabled'] && $connection['api_token_configured']) {
            // Deliver configured installations immediately; the outbox remains the retry path.
            $outbox->syncPending($centralClient, $ticket->church_id, 1, $ticket->id);
        }
        $this->notifySupportTeam($ticket, $request->user()->id);

        return redirect()->route('support.tickets.show', $ticket)->with('status', 'Your ticket was submitted. Track progress and replies here.');
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        $this->authorizeTicket($request, $ticket);
        $ticket->load([
            'church',
            'creator',
            'assignee',
            'attachments.uploader',
            'replies' => fn ($query) => $query
                ->when(! $request->user()->isSuperAdministrator(), fn ($replyQuery) => $replyQuery->where('is_internal', false))
                ->with(['user', 'attachments'])
                ->oldest(),
            'activities' => fn ($query) => $query
                ->when(! $request->user()->isSuperAdministrator(), fn ($activityQuery) => $activityQuery->where('type', '!=', 'internal_note'))
                ->with('user')
                ->oldest(),
        ]);

        return view('support.show', [
            'ticket' => $ticket,
            'categories' => self::CATEGORIES,
            'priorities' => self::PRIORITIES,
            'statuses' => self::STATUSES,
            'milestones' => $this->milestones($ticket),
            'supportUsers' => $request->user()->isSuperAdministrator()
                ? User::query()->where('status', 'active')->whereHas('roles', fn ($query) => $query->where('name', 'Super Administrator'))->orderBy('name')->get()
                : collect(),
            'canManage' => $request->user()->isSuperAdministrator(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Support Center', 'url' => route('support.index')],
                ['label' => $ticket->reference, 'url' => null],
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, ActivityLogger $activityLogger, CentralSupportOutbox $outbox): RedirectResponse
    {
        $this->authorizeTicket($request, $ticket);
        abort_if($ticket->status === 'closed', 422, 'Closed tickets cannot receive new replies.');
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,txt,csv,log', 'max:10240'],
        ]);
        $internal = $request->user()->isSuperAdministrator() && (bool) ($validated['is_internal'] ?? false);

        $reply = DB::transaction(function () use ($request, $ticket, $validated, $internal): SupportTicketReply {
            $reply = $ticket->replies()->create([
                'user_id' => $request->user()->id,
                'body' => $validated['body'],
                'is_internal' => $internal,
            ]);
            $updates = ['last_activity_at' => now()];
            if (! $internal && $request->user()->id !== $ticket->created_by && $ticket->first_response_at === null) {
                $updates['first_response_at'] = now();
                if ($ticket->status === 'new') {
                    $updates['status'] = 'triaged';
                    $updates['progress'] = max(20, $ticket->progress);
                }
            }
            $ticket->update($updates);
            $ticket->activities()->create([
                'user_id' => $request->user()->id,
                'type' => $internal ? 'internal_note' : 'reply',
                'description' => $internal ? 'An internal support note was added.' : 'A reply was added to the ticket.',
            ]);

            return $reply;
        });

        try {
            $this->storeAttachments($request->file('attachments', []), $ticket, $reply, $request->user()->id);
        } catch (Throwable $exception) {
            $reply->attachments()->get()->each(function (SupportTicketAttachment $attachment): void {
                Storage::disk($attachment->disk)->delete($attachment->path);
            });
            $reply->delete();
            report($exception);

            abort(503, 'The reply attachment could not be stored. Please try again.');
        }

        $activityLogger->log('Support', 'support_ticket_replied', 'Reply added to '.$ticket->reference.'.', $ticket, [
            'internal' => $internal,
            'status' => 'success',
            'risk' => 'low',
        ], $request);
        $outbox->enqueueReply($ticket, $reply->load('user'));

        if (! $internal) {
            $this->notifyReplyParticipants($ticket->fresh(), $request->user()->id);
        }

        return back()->with('status', $internal ? 'Internal note added.' : 'Reply sent.');
    }

    public function updateTracking(Request $request, SupportTicket $ticket, ActivityLogger $activityLogger, CentralSupportOutbox $outbox): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdministrator(), 403);
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(self::PRIORITIES))],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        if (filled($validated['assigned_to'] ?? null)) {
            abort_unless(User::query()->whereKey($validated['assigned_to'])->where('status', 'active')->whereHas('roles', fn ($query) => $query->where('name', 'Super Administrator'))->exists(), 422, 'Select an active support administrator.');
        }

        $before = $ticket->only(['status', 'priority', 'progress', 'assigned_to']);
        if (in_array($validated['status'], ['resolved', 'closed'], true)) {
            $validated['progress'] = 100;
        }
        $validated['first_response_at'] = $ticket->first_response_at ?? now();
        $validated['resolved_at'] = in_array($validated['status'], ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null;
        $validated['closed_at'] = $validated['status'] === 'closed' ? ($ticket->closed_at ?? now()) : null;
        $validated['last_activity_at'] = now();
        $ticket->update($validated);
        $ticket->activities()->create([
            'user_id' => $request->user()->id,
            'type' => 'tracking_updated',
            'description' => 'Tracking updated: '.self::STATUSES[$ticket->status].' at '.$ticket->progress.'%.',
            'metadata' => ['before' => $before, 'after' => $ticket->only(['status', 'priority', 'progress', 'assigned_to'])],
        ]);
        $activityLogger->log('Support', 'support_ticket_tracking_updated', 'Tracking updated for '.$ticket->reference.'.', $ticket, [
            'status' => $ticket->status,
            'progress' => $ticket->progress,
            'risk' => $ticket->priority,
        ], $request);
        $outbox->enqueueTracking($ticket);

        if ($ticket->creator && $ticket->created_by !== $request->user()->id) {
            $ticket->creator->notify(new SupportTicketNotification(
                $ticket,
                'Support ticket updated',
                $ticket->reference.' is now '.strtolower(self::STATUSES[$ticket->status]).' at '.$ticket->progress.'%.',
            ));
        }

        return back()->with('status', 'Ticket tracking updated.');
    }

    public function downloadAttachment(Request $request, SupportTicketAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing(['ticket', 'reply']);
        $this->authorizeTicket($request, $attachment->ticket);
        abort_if($attachment->reply?->is_internal && ! $request->user()->isSuperAdministrator(), 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function visibleTickets(Request $request): Builder
    {
        $query = SupportTicket::query();
        if ($request->user()->isSuperAdministrator()) {
            return $query;
        }
        if ($request->user()->hasPermission('manage settings') && $request->user()->church_id) {
            return $query->where('church_id', $request->user()->church_id);
        }

        return $query->where('created_by', $request->user()->id);
    }

    private function authorizeTicket(Request $request, SupportTicket $ticket): void
    {
        if ($request->user()->isSuperAdministrator()) {
            return;
        }
        $ownsTicket = $ticket->created_by === $request->user()->id;
        $managesChurch = $request->user()->church_id !== null
            && $ticket->church_id === $request->user()->church_id
            && $request->user()->hasPermission('manage settings');
        abort_unless($ownsTicket || $managesChurch, 404);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->string('status')->toString()))
            ->when($request->filled('category'), fn (Builder $builder) => $builder->where('category', $request->string('category')->toString()))
            ->when($request->filled('priority'), fn (Builder $builder) => $builder->where('priority', $request->string('priority')->toString()))
            ->when($request->filled('q'), function (Builder $builder) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('q')->trim()->toString()).'%';
                $builder->where(fn (Builder $search) => $search->where('reference', 'like', $term)->orWhere('subject', 'like', $term));
            });
    }

    private function ticketRules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(self::PRIORITIES))],
            'subject' => ['required', 'string', 'min:5', 'max:180'],
            'description' => ['required', 'string', 'min:20', 'max:20000'],
            'expected_outcome' => ['nullable', 'string', 'max:10000'],
            'page_url' => ['nullable', 'url', 'max:1000'],
            'browser' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,txt,csv,log', 'max:10240'],
        ];
    }

    private function newReference(): string
    {
        do {
            $reference = 'SUP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (SupportTicket::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function storeAttachments(array $files, SupportTicket $ticket, ?SupportTicketReply $reply, int $userId): void
    {
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $filename = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
            $hash = hash_file('sha256', $file->getRealPath());
            $path = $file->storeAs('support/'.$ticket->id, $filename, 'local');
            if (! is_string($path) || $path === '' || ! is_string($hash)) {
                throw new \RuntimeException('Support attachment storage failed.');
            }
            $ticket->attachments()->create([
                'support_ticket_reply_id' => $reply?->id,
                'uploaded_by' => $userId,
                'disk' => 'local',
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'sha256' => $hash,
            ]);
        }
    }

    private function deleteStoredAttachments(SupportTicket $ticket): void
    {
        $ticket->attachments()->get()->each(function (SupportTicketAttachment $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    private function notifySupportTeam(SupportTicket $ticket, int $exceptUserId): void
    {
        User::query()
            ->where('status', 'active')
            ->where('id', '!=', $exceptUserId)
            ->whereHas('roles', fn ($query) => $query->where('name', 'Super Administrator'))
            ->get()
            ->each(fn (User $user) => $user->notify(new SupportTicketNotification($ticket, 'New support ticket', $ticket->reference.': '.$ticket->subject)));
    }

    private function notifyReplyParticipants(SupportTicket $ticket, int $senderId): void
    {
        if ($ticket->created_by !== $senderId && $ticket->creator) {
            $ticket->creator->notify(new SupportTicketNotification($ticket, 'Support replied', $ticket->reference.' has a new reply.'));

            return;
        }
        if ($ticket->assigned_to && $ticket->assigned_to !== $senderId && $ticket->assignee) {
            $ticket->assignee->notify(new SupportTicketNotification($ticket, 'Church replied', $ticket->reference.' has a new reply.'));

            return;
        }

        $this->notifySupportTeam($ticket, $senderId);
    }

    private function milestones(SupportTicket $ticket): array
    {
        $status = $ticket->status;

        return [
            ['label' => 'Submitted', 'description' => 'Ticket received and given a tracking number.', 'complete' => true],
            ['label' => 'Reviewed', 'description' => 'Support has reviewed and categorized the request.', 'complete' => $status !== 'new'],
            ['label' => 'Work underway', 'description' => 'Investigation or implementation has started.', 'complete' => in_array($status, ['in_progress', 'waiting_on_church', 'resolved', 'closed'], true)],
            ['label' => 'Resolution provided', 'description' => 'A fix, answer, or delivery decision has been provided.', 'complete' => in_array($status, ['resolved', 'closed'], true)],
            ['label' => 'Closed', 'description' => 'The ticket is complete and archived.', 'complete' => $status === 'closed'],
        ];
    }
}
