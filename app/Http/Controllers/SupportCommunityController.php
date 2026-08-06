<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Church;
use App\Services\ActivityLogger;
use App\Services\CentralSupportClient;
use App\Services\CentralSupportSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

final class SupportCommunityController extends Controller
{
    private const CATEGORIES = [
        'bug' => 'Bug or error',
        'how_to' => 'How-to question',
        'idea' => 'Idea or suggestion',
        'feature' => 'Feature request',
        'integration' => 'Integration',
        'other' => 'Other',
    ];

    public function index(Request $request, CentralSupportSettings $settings, CentralSupportClient $client): View
    {
        $church = $this->church($request);
        $connection = $settings->forChurch($church);
        $community = ['data' => [], 'meta' => []];
        $unavailable = null;

        if ($connection['enabled'] && $connection['api_token_configured']) {
            try {
                $community = $client->communityQuestions($church, [
                    'q' => $request->string('q')->trim()->toString(),
                    'category' => $request->string('category')->toString(),
                    'status' => $request->string('status')->toString(),
                    'page' => max(1, $request->integer('page', 1)),
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $unavailable = 'Community Solutions is temporarily unavailable. Your private local tickets continue to work.';
            }
        } else {
            $unavailable = 'Connect this church to the central support server to access Community Solutions.';
        }

        return view('support.community', [
            'connection' => $connection,
            'questions' => collect($community['data'] ?? []),
            'meta' => (array) ($community['meta'] ?? []),
            'unavailable' => $unavailable,
            'categories' => self::CATEGORIES,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Support Center', 'url' => route('support.index')],
                ['label' => 'Community Solutions', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, CentralSupportSettings $settings, CentralSupportClient $client, ActivityLogger $logger): RedirectResponse
    {
        $church = $this->church($request);
        $connection = $settings->forChurch($church);
        abort_unless($connection['enabled'] && $connection['api_token_configured'], 422, 'Connect central support before publishing a community question.');
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'title' => ['required', 'string', 'min:10', 'max:180'],
            'body' => ['required', 'string', 'min:20', 'max:20000'],
            'consent' => ['accepted'],
        ]);

        try {
            $result = $client->createCommunityQuestion($church, [
                ...$validated,
                'consent' => true,
                'author' => [
                    'local_id' => $request->user()->opaqueId(),
                    'display_name' => $request->user()->name,
                ],
                'church' => [
                    'local_id' => $church->opaqueId(),
                    'display_name' => $church->name,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['community' => 'The question could not be sent to Community Solutions. Please try again later.']);
        }
        $logger->log('Support', 'community_question_created', 'A question was published to Community Solutions.', $church, [
            'central_question_id' => $result['id'] ?? null,
            'category' => $validated['category'],
        ], $request);

        return redirect()->route('support.community')->with('status', 'Your community question was published for review.');
    }

    private function church(Request $request): Church
    {
        return $request->user()?->church_id
            ? Church::query()->findOrFail($request->user()->church_id)
            : Church::query()->firstOrFail();
    }
}
