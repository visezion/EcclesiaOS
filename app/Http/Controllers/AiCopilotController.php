<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiCopilotConversation;
use App\Models\Church;
use App\Services\ActivityLogger;
use App\Services\AiProviderClient;
use App\Services\AiProviderSettings;
use App\Services\ChurchCopilotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\Table\TableExtension;

final class AiCopilotController extends Controller
{
    public function index(Request $request, ChurchCopilotService $copilot, AiProviderSettings $settings): View
    {
        $this->authorizeCopilot($request);
        $church = Church::query()->findOrFail($request->user()->church_id);

        $conversations = AiCopilotConversation::query()->where('church_id', $church->id)->where('user_id', $request->user()->id)->latest('last_message_at')->limit(20)->get()->map(fn (AiCopilotConversation $conversation): array => [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'updated' => $conversation->last_message_at?->diffForHumans() ?? $conversation->updated_at?->diffForHumans(),
            'messages' => collect($conversation->messages ?? [])->map(fn (array $message): array => ['role' => $message['role'] ?? 'assistant', 'text' => $message['text'] ?? null, 'html' => ($message['role'] ?? '') === 'assistant' ? $this->renderAnswer((string) ($message['text'] ?? '')) : null, 'time' => $message['time'] ?? ''])->values()->all(),
        ])->values()->all();

        return view('ai-copilot.index', ['settings' => $settings->get($church), 'providers' => config('ai.providers'), 'suggestedQuestions' => $copilot->suggestedQuestions(), 'conversations' => $conversations, 'activeConversation' => $conversations[0] ?? null, 'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'AI Copilot', 'url' => null]]]);
    }

    public function settings(Request $request, AiProviderSettings $settings): View
    {
        $this->authorizeSettings($request);
        $church = Church::query()->findOrFail($request->user()->church_id);

        return view('ai-copilot.settings', ['settings' => $settings->get($church), 'providers' => config('ai.providers'), 'breadcrumbs' => [['label' => 'Administration', 'url' => route('settings.index')], ['label' => 'AI Copilot Settings', 'url' => null]]]);
    }

    public function ask(Request $request, ChurchCopilotService $copilot, ActivityLogger $logger): View
    {
        $this->authorizeCopilot($request);
        $validated = $request->validate(['question' => ['required', 'string', 'max:500'], 'conversation_id' => ['nullable', 'integer']]);
        $conversation = ($validated['conversation_id'] ?? null)
            ? AiCopilotConversation::query()->where('id', $validated['conversation_id'])->where('church_id', $request->user()->church_id)->where('user_id', $request->user()->id)->firstOrFail()
            : AiCopilotConversation::query()->create(['church_id' => $request->user()->church_id, 'user_id' => $request->user()->id, 'title' => str($validated['question'])->limit(160), 'messages' => []]);
        $question = $this->resolveFollowUpQuestion($validated['question'], $conversation->messages ?? []);
        try {
            $result = $copilot->ask($request->user(), $question);
            $logger->log('AI Copilot', 'copilot_query', 'AI Copilot answered a controlled operational query.', null, ['intent' => $result['intent'], 'source_count' => $result['source_count'], 'provider' => data_get($request->user()->church?->settings, 'ai_copilot.provider')], $request);
        } catch (\Throwable $e) {
            report($e);
            $result = ['answer' => $e->getMessage(), 'intent' => 'error', 'source_count' => 0];
        }

        if (($result['intent'] ?? '') === 'error') {
            $result['answer_html'] = e((string) ($result['answer'] ?? 'The Copilot could not complete this request.'));
        } else {
            $converter = new CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
            $converter->getEnvironment()->addExtension(new TableExtension);
            $result['answer_html'] = $converter->convert((string) ($result['answer'] ?? ''))->getContent();
        }

        $messages = $conversation->messages ?? [];
        $messages[] = ['role' => 'user', 'text' => $validated['question'], 'time' => now()->format('h:i A')];
        $messages[] = ['role' => 'assistant', 'text' => (string) ($result['answer'] ?? ''), 'time' => now()->format('h:i A'), 'intent' => $result['intent'] ?? 'error'];
        $conversation->forceFill(['messages' => $messages, 'last_message_at' => now()])->save();

        return view('ai-copilot.partials.answer', ['question' => $validated['question'], 'result' => $result, 'conversationId' => $conversation->id]);
    }

    private function resolveFollowUpQuestion(string $question, array $messages): string
    {
        $isFollowUp = preg_match('/\b(this|current|last)\s+(month|moth|year)|\b(just|only)\b|\b(breakdown|compare|comparison|by fund|by method|by campus|trend)\b/i', $question) === 1;
        if (! $isFollowUp) {
            return $question;
        }

        $previous = collect($messages)->reverse()->first(fn ($message): bool => ($message['role'] ?? null) === 'user' && filled($message['text'] ?? null));
        if (! $previous) {
            return $question;
        }

        return "Previous report request: {$previous['text']}\nFollow-up scope or instruction: {$question}";
    }

    public function saveSettings(Request $request, AiProviderSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeSettings($request);
        $provider = (string) $request->input('provider');
        $validated = $request->validate(['provider' => ['required', 'in:openai,anthropic'], 'model' => ['required', 'string', 'max:100'], 'api_key' => ['nullable', 'string', 'max:500'], 'timeout' => ['required', 'integer', 'min:5', 'max:120'], 'max_tokens' => ['required', 'integer', 'min:200', 'max:8000']]);
        abort_unless(in_array($validated['model'], config("ai.providers.{$provider}.models", []), true), 422, 'That model is not available for the selected provider.');
        $settings->save(Church::query()->findOrFail($request->user()->church_id), $validated);
        $logger->log('Settings', 'ai_copilot_settings_updated', 'AI Copilot provider settings were updated.', null, ['provider' => $provider, 'model' => $validated['model']], $request);

        return back()->with('status', 'AI Copilot settings saved.');
    }

    public function testSettings(Request $request, AiProviderClient $client): RedirectResponse
    {
        $this->authorizeSettings($request);
        try {
            $client->test(Church::query()->findOrFail($request->user()->church_id));

            return back()->with('status', 'AI provider connection successful.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'AI provider connection failed: '.$e->getMessage());
        }
    }

    private function authorizeCopilot(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasAnyPermission(['use ai copilot', 'manage members', 'manage attendance', 'view finance', 'manage finance', 'view ministry finance', 'view leadership reports', 'view reports', 'manage events', 'manage prayer', 'manage volunteers', 'manage ministries', 'manage assets', 'manage facilities', 'manage communications', 'manage counselling', 'manage financial assistance', 'manage support', 'manage workflows', 'manage bookstore']), 403);
    }

    private function authorizeSettings(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage settings'), 403);
    }

    private function renderAnswer(string $answer): string
    {
        if ($answer === '') {
            return '<p>The Copilot returned an empty answer.</p>';
        }

        $converter = new CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $converter->getEnvironment()->addExtension(new TableExtension);

        return $converter->convert($answer)->getContent();
    }
}
