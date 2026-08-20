<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Church;
use App\Services\CentralSupportClient;
use App\Services\CentralSupportSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class SupportResourceController extends Controller
{
    public function knowledge(Request $request, CentralSupportSettings $settings, CentralSupportClient $client): View
    {
        $church = $this->church($request);
        $settings->autoEnroll($church);
        $connection = $settings->forChurch($church);
        $result = ['data' => [], 'meta' => [], 'categories' => []];
        $unavailable = null;

        if ($connection['enabled'] && $connection['api_token_configured']) {
            try {
                $result = $client->knowledgeArticles($church, [
                    'q' => $request->string('q')->trim()->toString(),
                    'category' => $request->string('category')->toString(),
                    'sort' => $request->string('sort', 'relevant')->toString(),
                    'page' => max(1, $request->integer('page', 1)),
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $unavailable = 'The central knowledge base is temporarily unavailable.';
            }
        } else {
            $unavailable = 'Connect this church to central support to browse official guides.';
        }

        return view('support.knowledge', [
            'connection' => $connection,
            'articles' => collect($result['data'] ?? []),
            'meta' => (array) ($result['meta'] ?? []),
            'categories' => collect($result['categories'] ?? []),
            'unavailable' => $unavailable,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Support Center', 'url' => route('support.index')],
                ['label' => 'Knowledge Base', 'url' => null],
            ],
        ]);
    }

    public function knowledgeArticle(Request $request, string $article, CentralSupportSettings $settings, CentralSupportClient $client): View
    {
        $church = $this->church($request);
        $settings->autoEnroll($church);
        $connection = $settings->forChurch($church);
        $articleData = null;
        $unavailable = null;

        if ($connection['enabled'] && $connection['api_token_configured']) {
            try {
                $result = $client->knowledgeArticle($church, $article);
                $articleData = (array) ($result['data'] ?? $result['article'] ?? $result);
            } catch (Throwable $exception) {
                report($exception);
                $unavailable = 'This knowledge-base article is temporarily unavailable.';
            }
        } else {
            $unavailable = 'Connect this church to central support to read the full article.';
        }

        return view('support.knowledge-article', [
            'connection' => $connection,
            'article' => $articleData,
            'articleId' => $article,
            'unavailable' => $unavailable,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Support Center', 'url' => route('support.index')],
                ['label' => 'Knowledge Base', 'url' => route('support.knowledge')],
                ['label' => $articleData['title'] ?? 'Article', 'url' => null],
            ],
        ]);
    }

    public function rateKnowledgeArticle(Request $request, string $article, CentralSupportSettings $settings, CentralSupportClient $client): RedirectResponse
    {
        $church = $this->church($request);
        $settings->autoEnroll($church);
        $connection = $settings->forChurch($church);
        abort_unless($connection['enabled'] && $connection['api_token_configured'], 422, 'Connect central support before rating an article.');
        $validated = $request->validate(['helpful' => ['required', 'boolean']]);

        try {
            $client->rateKnowledgeArticle($church, $article, (bool) $validated['helpful']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['article' => 'Your article feedback could not be saved. Please try again.']);
        }

        return back()->with('status', 'Thank you. Your feedback helps improve the knowledge base.');
    }

    public function live(Request $request, CentralSupportSettings $settings, CentralSupportClient $client): View
    {
        $church = $this->church($request);
        $settings->autoEnroll($church);
        $connection = $settings->forChurch($church);
        $live = ['online' => false, 'queue_position' => null, 'average_response' => null, 'messages' => [], 'suggested_articles' => []];
        $unavailable = null;

        if ($connection['enabled'] && $connection['api_token_configured']) {
            try {
                $live = [...$live, ...$client->liveSupport($church)];
            } catch (Throwable $exception) {
                report($exception);
                $unavailable = 'Live support is temporarily unavailable. You can still submit a private ticket.';
            }
        } else {
            $unavailable = 'Connect this church to central support to start a live conversation.';
        }

        return view('support.live', [
            'connection' => $connection,
            'live' => $live,
            'unavailable' => $unavailable,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Support Center', 'url' => route('support.index')],
                ['label' => 'Live Support', 'url' => null],
            ],
        ]);
    }

    public function sendLiveMessage(Request $request, CentralSupportSettings $settings, CentralSupportClient $client): RedirectResponse
    {
        $church = $this->church($request);
        $settings->autoEnroll($church);
        $connection = $settings->forChurch($church);
        abort_unless($connection['enabled'] && $connection['api_token_configured'], 422, 'Connect central support before starting live support.');
        $validated = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);

        try {
            $client->sendLiveMessage($church, [
                'message' => $validated['message'],
                'author' => ['local_id' => $request->user()->opaqueId(), 'display_name' => $request->user()->name],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['live' => 'Your message could not be sent. Submit a ticket if the problem continues.']);
        }

        return back()->with('status', 'Message sent to central support.');
    }

    private function church(Request $request): Church
    {
        return $request->user()?->church_id
            ? Church::query()->findOrFail($request->user()->church_id)
            : Church::query()->firstOrFail();
    }
}
