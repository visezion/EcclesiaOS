<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\CareTask;
use App\Models\Event;
use App\Models\Facility;
use App\Models\Family;
use App\Models\FinanceTransaction;
use App\Models\LeadershipReport;
use App\Models\Member;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageDraft;
use App\Models\MessageReport;
use App\Models\MessageThread;
use App\Models\Ministry;
use App\Models\PrayerRequest;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\NewMessageNotification;
use App\Services\ActivityLogger;
use App\Services\Messages\MessageAuditLogger;
use App\Services\Messages\MessageContent;
use App\Services\Messages\MessageRecipientResolver;
use App\Support\OpaqueId;
use App\Support\UnreadCounts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeMessages($request);

        return $this->messageCenter($request);
    }

    public function sent(Request $request): View
    {
        $this->authorizeMessages($request);

        return $this->messageCenter($request, initialFolder: 'sent');
    }

    public function create(Request $request): View
    {
        $this->authorizeSending($request);

        return $this->messageCenter(
            $request,
            composeOpen: true,
            composeRecipient: $this->composeRecipient($request),
        );
    }

    public function store(
        Request $request,
        ActivityLogger $activityLogger,
        MessageRecipientResolver $resolver,
        MessageContent $content,
        MessageAuditLogger $audit,
    ): RedirectResponse {
        $this->authorizeSending($request);

        $validated = $request->validate([
            'recipients' => ['required', 'array', 'min:1', 'max:50'],
            'recipients.*' => ['string', 'max:1000'],
            'subject' => ['nullable', 'string', 'max:160'],
            'body' => ['required_without:body_html', 'nullable', 'string', 'max:50000'],
            'body_html' => ['nullable', 'string', 'max:100000'],
            'conversation_type' => ['nullable', 'in:private,group,ministry,department,campus,role,leadership,event,task,report,approval,announcement'],
            'permission_scope' => ['nullable', 'in:church,campus,ministry,leadership,restricted'],
            'linked_type' => ['nullable', 'in:event,report,approval,member,family,program,attendance,volunteer,task,facility,finance,prayer'],
            'linked_id' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'draft_id' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,gif'],
        ]);

        $resolved = $resolver->resolve($request->user(), $validated['recipients']);
        $recipients = $resolved['users'];
        $type = $validated['conversation_type'] ?? ($recipients->count() > 1 ? 'group' : 'private');
        $permissionScope = $validated['permission_scope'] ?? 'church';
        abort_if(in_array($type, ['group', 'role', 'ministry', 'campus', 'leadership', 'announcement'], true)
            && ! $request->user()->isSuperAdministrator()
            && ! $request->user()->hasPermission('create message groups'), 403);
        abort_if(in_array($permissionScope, ['leadership', 'restricted'], true)
            && $recipients->contains(fn (User $recipient): bool => ! $recipient->isSuperAdministrator() && ! $recipient->hasPermission('view sensitive messages')),
            422,
            'Every recipient of a sensitive conversation must have permission to view sensitive messages.');

        $bodyHtml = $content->sanitize($validated['body_html'] ?? null, (string) ($validated['body'] ?? ''));
        $body = $content->plainText($bodyHtml);
        $scheduledAt = filled($validated['scheduled_at'] ?? null) ? Carbon::parse($validated['scheduled_at']) : null;
        [$linkedType, $linkedId, $linkedLabel] = $this->resolveLinkedRecord($request, $validated['linked_type'] ?? null, $validated['linked_id'] ?? null);

        [$thread, $message] = DB::transaction(function () use ($request, $validated, $resolved, $recipients, $type, $permissionScope, $body, $bodyHtml, $scheduledAt, $linkedType, $linkedId, $linkedLabel): array {
            $now = now();
            $thread = MessageThread::query()->create([
                'church_id' => $request->user()->church_id,
                'created_by' => $request->user()->id,
                'subject' => filled($validated['subject'] ?? null) ? $validated['subject'] : null,
                'type' => $type,
                'status' => 'active',
                'permission_scope' => $permissionScope,
                'linked_type' => $linkedType,
                'linked_id' => $linkedId,
                'linked_label' => $linkedLabel,
                'metadata' => $scheduledAt ? ['scheduled_recipient_ids' => $recipients->pluck('id')->all()] : null,
                'last_message_at' => $scheduledAt ?? $now,
                'retention_until' => $now->copy()->addDays((int) config('messages.retention_days', 2555)),
            ]);
            $participants = [
                $request->user()->id => [
                    'participant_role' => 'administrator',
                    'last_read_at' => $now,
                    'joined_at' => $now,
                ],
            ];
            if (! $scheduledAt) {
                foreach ($recipients as $recipient) {
                    $participants[$recipient->id] = [
                        'participant_role' => 'member',
                        'last_read_at' => null,
                        'joined_at' => $now,
                    ];
                }
            }
            $thread->participants()->attach($participants);
            $thread->recipients()->createMany($resolved['recipients']);
            $message = $thread->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $body,
                'body_html' => $bodyHtml,
                'status' => $scheduledAt ? 'scheduled' : 'sent',
                'scheduled_at' => $scheduledAt,
                'sent_at' => $scheduledAt ? null : $now,
            ]);

            return [$thread, $message];
        });

        $this->storeAttachments($request, $message);
        $audit->record($scheduledAt ? 'message_scheduled' : 'message_created', $thread, $message, ['recipient_count' => $recipients->count(), 'type' => $type], $request);
        if (! $scheduledAt) {
            $this->notifyRecipients($recipients, $thread, $request->user()->name, $body, $message->attachments()->count());
        }
        $activityLogger->log('Messages', $scheduledAt ? 'message_scheduled' : 'message_sent', $scheduledAt ? 'An internal message was scheduled.' : 'An internal message was sent.', $thread, ['resource' => 'Message Thread', 'status' => 'success'], $request);
        if (filled($validated['draft_id'] ?? null)) {
            $draftId = OpaqueId::decode($validated['draft_id'], MessageDraft::class);
            MessageDraft::query()
                ->whereKey($draftId)
                ->where('church_id', $request->user()->church_id)
                ->where('user_id', $request->user()->id)
                ->delete();
        }

        return redirect()->route('messages.show', $thread)->with('status', $scheduledAt ? 'Message scheduled.' : 'Message sent.');
    }

    public function show(Request $request, MessageThread $thread): View
    {
        $this->authorizeThread($request, $thread);
        $thread->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);

        return $this->messageCenter($request, $thread);
    }

    public function reply(Request $request, MessageThread $thread, MessageContent $content, MessageAuditLogger $audit): RedirectResponse
    {
        $this->authorizeThread($request, $thread);
        abort_if($thread->status !== 'active', 422, 'This conversation is not active.');
        abort_if($thread->replies_restricted && ! $this->canManageThread($request, $thread), 403);
        $validated = $request->validate([
            'body' => ['required_without:body_html', 'nullable', 'string', 'max:50000'],
            'body_html' => ['nullable', 'string', 'max:100000'],
            'is_internal_note' => ['sometimes', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,gif'],
        ]);
        $bodyHtml = $content->sanitize($validated['body_html'] ?? null, (string) ($validated['body'] ?? ''));
        $message = $thread->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $content->plainText($bodyHtml),
            'body_html' => $bodyHtml,
            'is_internal_note' => (bool) ($validated['is_internal_note'] ?? false),
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $this->storeAttachments($request, $message);
        $thread->forceFill(['last_message_at' => now()])->save();
        $thread->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);
        $audit->record('message_replied', $thread, $message, ['internal_note' => $message->is_internal_note], $request);
        $this->notifyRecipients(
            $thread->participants()->where('users.id', '!=', $request->user()->id)->get(),
            $thread,
            $request->user()->name,
            $message->body,
            $message->attachments()->count(),
        );

        return redirect()->route('messages.show', $thread)->with('status', 'Reply sent.');
    }

    public function read(Request $request, MessageThread $thread, MessageAuditLogger $audit): JsonResponse
    {
        $this->authorizeThread($request, $thread);
        $thread->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);
        $audit->record('conversation_viewed', $thread, request: $request);

        return response()->json(['status' => 'ok']);
    }

    public function state(Request $request, MessageThread $thread, MessageAuditLogger $audit): JsonResponse
    {
        $this->authorizeThread($request, $thread);
        $validated = $request->validate([
            'state' => ['required', 'in:starred,archived,read'],
            'enabled' => ['required', 'boolean'],
        ]);
        $column = $validated['state'] === 'read' ? 'last_read_at' : $validated['state'].'_at';
        $thread->participants()->updateExistingPivot($request->user()->id, [
            $column => $validated['enabled'] ? now() : null,
        ]);
        $audit->record('conversation_'.$validated['state'].'_changed', $thread, metadata: ['enabled' => $validated['enabled']], request: $request);

        return response()->json(['status' => 'ok']);
    }

    public function action(Request $request, MessageThread $thread, MessageAuditLogger $audit): JsonResponse
    {
        $this->authorizeThread($request, $thread);
        $validated = $request->validate(['action' => ['required', 'in:close,reopen,delete,leave,restrict,unrestrict']]);
        $action = $validated['action'];

        if ($action === 'leave') {
            abort_if($thread->created_by === $request->user()->id && $thread->participants()->count() > 1, 422, 'Assign another administrator before leaving.');
            $thread->participants()->updateExistingPivot($request->user()->id, ['left_at' => now()]);
        } else {
            abort_unless($this->canManageThread($request, $thread), 403);
            match ($action) {
                'close' => $thread->forceFill(['status' => 'closed', 'closed_by' => $request->user()->id, 'closed_at' => now()])->save(),
                'reopen' => $thread->forceFill(['status' => 'active', 'closed_by' => null, 'closed_at' => null])->save(),
                'delete' => $thread->delete(),
                'restrict' => $thread->forceFill(['replies_restricted' => true])->save(),
                'unrestrict' => $thread->forceFill(['replies_restricted' => false])->save(),
            };
        }

        $audit->record('conversation_'.$action, $thread, request: $request);

        return response()->json(['status' => 'ok']);
    }

    public function saveDraft(Request $request, ?MessageDraft $draft = null): JsonResponse
    {
        $this->authorizeSending($request);
        if ($draft) {
            abort_unless($draft->church_id === $request->user()->church_id && $draft->user_id === $request->user()->id, 404);
        }
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:50000'],
            'body_html' => ['nullable', 'string', 'max:100000'],
            'recipients' => ['nullable', 'array', 'max:50'],
            'recipients.*' => ['string', 'max:1000'],
            'conversation_type' => ['nullable', 'string', 'max:32'],
            'linked_type' => ['nullable', 'string', 'max:64'],
            'linked_id' => ['nullable', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        $draft ??= new MessageDraft([
            'church_id' => $request->user()->church_id,
            'user_id' => $request->user()->id,
        ]);
        $draft->fill($validated);
        $draft->save();

        return response()->json(['status' => 'ok', 'id' => $draft->opaqueId(), 'updated_at' => $draft->updated_at?->toIso8601String()]);
    }

    public function deleteDraft(Request $request, MessageDraft $draft): JsonResponse
    {
        $this->authorizeSending($request);
        abort_unless($draft->church_id === $request->user()->church_id && $draft->user_id === $request->user()->id, 404);
        $draft->attachments->each(fn (MessageAttachment $attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
        $draft->delete();

        return response()->json(['status' => 'ok']);
    }

    public function participants(Request $request, MessageThread $thread, MessageRecipientResolver $resolver, MessageAuditLogger $audit): JsonResponse
    {
        $this->authorizeThread($request, $thread);
        abort_unless($this->canManageThread($request, $thread), 403);
        $validated = $request->validate([
            'action' => ['required', 'in:add,remove,promote,demote'],
            'recipients' => ['nullable', 'array', 'max:50'],
            'recipients.*' => ['string'],
            'user' => ['nullable', 'string'],
        ]);

        if ($validated['action'] === 'add') {
            $resolved = $resolver->resolve($request->user(), $validated['recipients'] ?? []);
            foreach ($resolved['users'] as $user) {
                $thread->participants()->syncWithoutDetaching([$user->id => ['participant_role' => 'member', 'joined_at' => now(), 'left_at' => null]]);
                $user->notify(new NewMessageNotification($thread, $request->user()->name, 'You were added to a conversation.'));
            }
        } else {
            $userId = OpaqueId::decode($validated['user'] ?? '', User::class);
            $participant = $thread->participants()->whereKey($userId)->firstOrFail();
            abort_if($participant->id === $thread->created_by && $validated['action'] === 'remove', 422, 'The conversation creator cannot be removed.');
            $updates = match ($validated['action']) {
                'remove' => ['left_at' => now()],
                'promote' => ['participant_role' => 'administrator'],
                'demote' => ['participant_role' => 'member'],
            };
            $thread->participants()->updateExistingPivot($participant->id, $updates);
        }

        $audit->record('participants_'.$validated['action'], $thread, metadata: ['user' => $validated['user'] ?? null], request: $request);

        return response()->json(['status' => 'ok']);
    }

    public function forward(Request $request, Message $message, MessageRecipientResolver $resolver, MessageAuditLogger $audit): RedirectResponse
    {
        $thread = $message->thread;
        $this->authorizeThread($request, $thread);
        abort_unless($request->user()->isSuperAdministrator() || $request->user()->hasPermission('forward messages'), 403);
        abort_if($thread->permission_scope === 'restricted', 403, 'Restricted conversations cannot be forwarded.');
        $validated = $request->validate(['recipients' => ['required', 'array', 'min:1', 'max:50'], 'recipients.*' => ['string']]);
        $resolved = $resolver->resolve($request->user(), $validated['recipients']);

        $newThread = DB::transaction(function () use ($request, $message, $resolved): MessageThread {
            $newThread = MessageThread::query()->create([
                'church_id' => $request->user()->church_id,
                'created_by' => $request->user()->id,
                'subject' => 'Fwd: '.($message->thread->subject ?: 'Conversation'),
                'type' => $resolved['users']->count() > 1 ? 'group' : 'private',
                'status' => 'active',
                'last_message_at' => now(),
            ]);
            $participants = [$request->user()->id => ['participant_role' => 'administrator', 'last_read_at' => now(), 'joined_at' => now()]];
            foreach ($resolved['users'] as $recipient) {
                $participants[$recipient->id] = [
                    'participant_role' => 'member',
                    'last_read_at' => null,
                    'joined_at' => now(),
                ];
            }
            $newThread->participants()->attach($participants);
            $newThread->recipients()->createMany($resolved['recipients']);
            $forward = $newThread->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $message->body,
                'body_html' => $message->body_html,
                'status' => 'sent',
                'sent_at' => now(),
                'forwarded_from_id' => $message->id,
            ]);
            foreach ($message->attachments as $attachment) {
                $path = 'message-attachments/'.$request->user()->church_id.'/'.Str::uuid().'-'.Str::slug(pathinfo($attachment->original_name, PATHINFO_FILENAME)).'.'.pathinfo($attachment->original_name, PATHINFO_EXTENSION);
                abort_unless(Storage::disk('local')->copy($attachment->path, $path), 500, 'The forwarded attachment could not be copied.');
                $forward->attachments()->create(array_merge($attachment->only(['church_id', 'disk', 'original_name', 'mime_type', 'size', 'sha256', 'is_image']), ['uploaded_by' => $request->user()->id, 'path' => $path]));
            }

            return $newThread;
        });

        $audit->record('message_forwarded', $newThread, $newThread->messages()->first(), ['source_message_id' => $message->id], $request);
        $resolved['users']->each->notify(new NewMessageNotification($newThread, $request->user()->name));

        return redirect()->route('messages.show', $newThread)->with('status', 'Message forwarded.');
    }

    public function downloadAttachment(Request $request, MessageAttachment $attachment): Response|StreamedResponse
    {
        $message = $attachment->message;
        abort_unless($message, 404);
        $this->authorizeThread($request, $message->thread);
        abort_unless($attachment->church_id === $request->user()->church_id && Storage::disk($attachment->disk)->exists($attachment->path), 404);
        $previewableMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        if ($request->boolean('preview') && in_array($attachment->mime_type, $previewableMimeTypes, true)) {
            return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type]);
    }

    public function export(Request $request, MessageThread $thread, MessageAuditLogger $audit): StreamedResponse
    {
        $this->authorizeThread($request, $thread);
        abort_unless($request->user()->isSuperAdministrator() || $request->user()->hasPermission('export message history'), 403);
        $thread->load('messages.sender');
        $audit->record('conversation_exported', $thread, request: $request);

        return response()->streamDownload(function () use ($thread): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Conversation', $thread->subject, 'ID', $thread->id]);
            fputcsv($handle, ['Sender', 'Date', 'Type', 'Message']);
            foreach ($thread->messages as $message) {
                fputcsv($handle, [$message->sender->name, $message->created_at?->toIso8601String(), $message->is_internal_note ? 'Internal note' : 'Message', $message->body]);
            }
            fclose($handle);
        }, 'conversation-'.$thread->id.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function audit(Request $request, MessageThread $thread): JsonResponse
    {
        $this->authorizeThread($request, $thread);
        abort_unless($this->canManageThread($request, $thread) || $request->user()->hasPermission('view audit log'), 403);
        $events = $thread->auditEvents()->with('actor:id,name')->latest('created_at')->limit(250)->get();

        return response()->json(['events' => $events]);
    }

    public function report(Request $request, MessageThread $thread, MessageAuditLogger $audit): JsonResponse
    {
        $this->authorizeThread($request, $thread);
        $validated = $request->validate([
            'message' => ['nullable', 'string'],
            'reason' => ['required', 'in:abuse,harassment,spam,sensitive_data,policy_violation,other'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);
        $messageId = filled($validated['message'] ?? null) ? OpaqueId::decode($validated['message'], Message::class) : null;
        if ($messageId) {
            abort_unless($thread->messages()->whereKey($messageId)->exists(), 422);
        }
        MessageReport::query()->create([
            'church_id' => $thread->church_id,
            'message_thread_id' => $thread->id,
            'message_id' => $messageId,
            'reported_by' => $request->user()->id,
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
        ]);
        $audit->record('conversation_reported', $thread, metadata: ['reason' => $validated['reason']], request: $request);

        return response()->json(['status' => 'ok']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorizeMessages($request);
        DB::table('message_thread_user')
            ->where('user_id', $request->user()->id)
            ->whereIn('message_thread_id', $this->threadsFor($request)->select('message_threads.id'))
            ->update(['last_read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    private function threadsFor(Request $request): Builder
    {
        $query = MessageThread::query()
            ->where('church_id', $request->user()->church_id)
            ->whereHas('participants', fn (Builder $query) => $query->where('users.id', $request->user()->id));

        if (! $request->user()->isSuperAdministrator() && ! $request->user()->hasPermission('view sensitive messages')) {
            $query->where(function (Builder $query) use ($request): void {
                $query
                    ->whereNotIn('permission_scope', ['leadership', 'restricted'])
                    ->orWhere('created_by', $request->user()->id);
            });
        }

        return $query;
    }

    private function recipientQuery(Request $request): Builder
    {
        return User::query()
            ->where('church_id', $request->user()->church_id)
            ->where('status', 'active')
            ->where('id', '!=', $request->user()->id);
    }

    private function unreadCount(Request $request): int
    {
        return app(UnreadCounts::class)->messages($request->user());
    }

    private function messageCenter(
        Request $request,
        ?MessageThread $selectedThread = null,
        string $initialFolder = 'inbox',
        bool $composeOpen = false,
        ?string $composeRecipient = null,
    ): View {
        $threads = $this->threadsFor($request)
            ->with(['participants.roles', 'creator', 'messages.sender.roles', 'messages.attachments', 'latestMessage.sender', 'recipients'])
            ->withCount(['messages', 'auditEvents'])
            ->latest('last_message_at')
            ->limit(100)
            ->get();

        return view('messages.index', [
            'threads' => $threads,
            'users' => $this->recipientQuery($request)->orderBy('name')->get(),
            'roles' => Role::query()->whereHas('users', fn (Builder $query) => $query->where('church_id', $request->user()->church_id)->where('status', 'active'))->orderBy('name')->get(),
            'campuses' => Campus::query()->where('church_id', $request->user()->church_id)->where('status', 'active')->orderBy('name')->get(),
            'ministries' => Ministry::query()->where('church_id', $request->user()->church_id)->where('status', 'active')->orderBy('name')->get(),
            'drafts' => MessageDraft::query()->where('church_id', $request->user()->church_id)->where('user_id', $request->user()->id)->latest('updated_at')->get(),
            'linkableRecords' => $this->linkableRecords($request),
            'selectedThreadId' => $selectedThread?->id ?? $threads->first()?->id,
            'initialFolder' => $initialFolder,
            'composeOpen' => $composeOpen || $request->hasSession() && $request->session()->getOldInput() !== [],
            'composeRecipient' => $composeRecipient,
            'canSendMessages' => $request->user()->isSuperAdministrator() || $request->user()->hasPermission('send messages'),
            'unreadCount' => $this->unreadCount($request),
            'stats' => $this->messageStats($request),
            'breadcrumbs' => $this->breadcrumbs('Message Center'),
        ]);
    }

    private function composeRecipient(Request $request): ?string
    {
        $recipient = (string) $request->query('recipient', '');
        if (! str_starts_with($recipient, 'user:')) {
            return null;
        }

        $userId = OpaqueId::decode(substr($recipient, 5), User::class);
        if (! $userId) {
            abort(404);
        }

        $user = $this->recipientQuery($request)->whereKey($userId)->first();
        if (! $user) {
            abort(404);
        }

        return 'user:'.$user->opaqueId();
    }

    private function messageStats(Request $request): array
    {
        $threads = $this->threadsFor($request);

        return [
            'conversations' => (clone $threads)->count(),
            'active' => (clone $threads)->where('status', 'active')->count(),
            'sent' => (clone $threads)
                ->whereHas('messages', fn (Builder $query) => $query->where('sender_id', $request->user()->id))
                ->count(),
            'members' => $this->recipientQuery($request)->count(),
            'drafts' => MessageDraft::query()->where('church_id', $request->user()->church_id)->where('user_id', $request->user()->id)->count(),
            'messages' => Message::query()
                ->whereIn('message_thread_id', (clone $threads)->select('message_threads.id'))
                ->where('status', 'sent')
                ->count(),
            'storage' => MessageAttachment::query()
                ->whereHas('message', fn (Builder $query) => $query->whereIn('message_thread_id', (clone $threads)->select('message_threads.id')))
                ->sum('size'),
            'response_minutes' => $this->averageResponseMinutes($request),
        ];
    }

    private function averageResponseMinutes(Request $request): int
    {
        $messages = Message::query()
            ->where('status', 'sent')
            ->whereIn('message_thread_id', $this->threadsFor($request)->select('message_threads.id'))
            ->latest('created_at')
            ->limit(500)
            ->get(['message_thread_id', 'sender_id', 'created_at'])
            ->groupBy('message_thread_id');
        $responses = collect();
        foreach ($messages as $threadMessages) {
            $ordered = $threadMessages->sortBy('created_at')->values();
            for ($index = 1; $index < $ordered->count(); $index++) {
                if ($ordered[$index]->sender_id !== $ordered[$index - 1]->sender_id) {
                    $responses->push($ordered[$index - 1]->created_at->diffInMinutes($ordered[$index]->created_at));
                }
            }
        }

        return (int) round($responses->average() ?? 0);
    }

    private function authorizeMessages(Request $request): void
    {
        abort_unless($request->user() && $request->user()->status === 'active', 403);
    }

    private function authorizeSending(Request $request): void
    {
        $this->authorizeMessages($request);
        abort_unless($request->user()->isSuperAdministrator() || $request->user()->hasPermission('send messages'), 403);
    }

    private function authorizeThread(Request $request, MessageThread $thread): void
    {
        $this->authorizeMessages($request);
        abort_unless($thread->church_id === $request->user()->church_id && $thread->participants()->whereKey($request->user()->id)->exists(), 404);
        abort_if(in_array($thread->permission_scope, ['leadership', 'restricted'], true)
            && ! $request->user()->isSuperAdministrator()
            && ! $request->user()->hasPermission('view sensitive messages')
            && $thread->created_by !== $request->user()->id, 403);
    }

    private function canManageThread(Request $request, MessageThread $thread): bool
    {
        if ($request->user()->isSuperAdministrator() || $request->user()->hasPermission('administer messages') || $thread->created_by === $request->user()->id) {
            return true;
        }

        return $thread->participants()
            ->whereKey($request->user()->id)
            ->wherePivot('participant_role', 'administrator')
            ->exists();
    }

    private function storeAttachments(Request $request, Message $message): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs(
                'message-attachments/'.$request->user()->church_id,
                Str::uuid().($extension ? '.'.$extension : ''),
                'local',
            );
            abort_unless($path, 500, 'The attachment could not be stored.');
            $message->attachments()->create([
                'church_id' => $request->user()->church_id,
                'uploaded_by' => $request->user()->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'is_image' => str_starts_with((string) $file->getMimeType(), 'image/'),
            ]);
        }
    }

    private function notifyRecipients($recipients, MessageThread $thread, string $senderName, string $body, int $attachmentCount = 0): void
    {
        foreach ($recipients as $recipient) {
            $firstName = strtolower(strtok($recipient->name, ' '));
            $mentioned = str_contains(strtolower($body), '@'.strtolower($recipient->name))
                || str_contains(strtolower($body), '@'.$firstName);
            $customMessage = match (true) {
                $mentioned => $senderName.' mentioned you in '.($thread->subject ?: 'a conversation').'.',
                $attachmentCount > 0 => $senderName.' sent a message with '.Str::plural('attachment', $attachmentCount).'.',
                default => null,
            };
            $recipient->notify(new NewMessageNotification($thread, $senderName, $customMessage));
        }
    }

    private function resolveLinkedRecord(Request $request, ?string $type, ?string $opaqueId): array
    {
        if (! filled($type) || ! filled($opaqueId)) {
            return [null, null, null];
        }

        $classes = [
            'event' => Event::class,
            'report' => LeadershipReport::class,
            'approval' => Approval::class,
            'member' => Member::class,
            'family' => Family::class,
            'program' => Program::class,
            'attendance' => AttendanceSession::class,
            'volunteer' => Volunteer::class,
            'task' => CareTask::class,
            'facility' => Facility::class,
            'finance' => FinanceTransaction::class,
            'prayer' => PrayerRequest::class,
        ];
        $class = $classes[$type] ?? null;
        abort_unless($class, 422);
        $id = OpaqueId::decode($opaqueId, $class);
        $record = $class::query()->where('church_id', $request->user()->church_id)->find($id);
        abort_unless($record, 422, 'The linked record is unavailable.');
        $label = $record->title ?? $record->name ?? $record->action ?? $record->reference ?? class_basename($record).' #'.$record->id;

        return [$type, $record->id, (string) $label];
    }

    private function linkableRecords(Request $request): array
    {
        return [
            'event' => Event::query()->where('church_id', $request->user()->church_id)->latest('starts_at')->limit(30)->get()->map(fn (Event $event): array => ['id' => $event->opaqueId(), 'label' => $event->title])->all(),
            'report' => LeadershipReport::query()->where('church_id', $request->user()->church_id)->latest()->limit(30)->get()->map(fn (LeadershipReport $report): array => ['id' => $report->opaqueId(), 'label' => $report->title])->all(),
            'approval' => Approval::query()->where('church_id', $request->user()->church_id)->latest()->limit(30)->get()->map(fn (Approval $approval): array => ['id' => $approval->opaqueId(), 'label' => $approval->action])->all(),
        ];
    }

    private function breadcrumbs(string $label): array
    {
        return [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Messages', 'url' => route('messages.index')],
            ['label' => $label, 'url' => null],
        ];
    }
}
