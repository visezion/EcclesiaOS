<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Sermon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class SermonController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeMedia($request);
        $church = $this->churchForRequest($request);

        return view('sermons.index', [
            'church' => $church,
            'sermons' => $church->sermons()->latest('preached_at')->latest('id')->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Sermons & Media', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeMedia($request);
        $church = $this->churchForRequest($request);
        $data = $this->validatedSermon($request, $church);
        if ($request->hasFile('video_file')) {
            $data['video_url'] = $request->file('video_file')->store('website/'.$church->id.'/sermons', 'public');
        }
        if ($request->hasFile('thumbnail_file')) {
            $data['thumbnail_url'] = $request->file('thumbnail_file')->store('website/'.$church->id.'/sermons', 'public');
        }
        $data['church_id'] = $church->id;
        Sermon::query()->create($data);

        return back()->with('status', 'Sermon published to the library.');
    }

    public function update(Request $request, Sermon $sermon): RedirectResponse
    {
        $this->authorizeMedia($request);
        $this->authorizeSermon($request, $sermon);
        $data = $this->validatedSermon($request, $sermon->church, $sermon);
        if ($request->hasFile('video_file')) {
            $data['video_url'] = $request->file('video_file')->store('website/'.$sermon->church_id.'/sermons', 'public');
        }
        if ($request->hasFile('thumbnail_file')) {
            $data['thumbnail_url'] = $request->file('thumbnail_file')->store('website/'.$sermon->church_id.'/sermons', 'public');
        }
        $sermon->update($data);

        return back()->with('status', 'Sermon updated.');
    }

    public function destroy(Request $request, Sermon $sermon): RedirectResponse
    {
        $this->authorizeMedia($request);
        $this->authorizeSermon($request, $sermon);
        $sermon->delete();

        return back()->with('status', 'Sermon archived.');
    }

    private function authorizeMedia(Request $request): void
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || $user?->hasPermission('manage media'), 403);
    }

    private function authorizeSermon(Request $request, Sermon $sermon): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $sermon->church_id === $request->user()?->church_id, 404);
    }

    private function churchForRequest(Request $request): Church
    {
        $church = $request->user()?->church_id
            ? Church::query()->find($request->user()->church_id)
            : null;

        return $church ?? Church::query()->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validatedSermon(Request $request, Church $church, ?Sermon $sermon = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'alpha_dash', 'max:120'],
            'speaker' => ['nullable', 'string', 'max:160'],
            'scripture' => ['nullable', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'preached_at' => ['nullable', 'date'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'video_file' => ['nullable', 'mimetypes:video/mp4,video/webm,video/ogg', 'max:51200'],
            'audio_url' => ['nullable', 'url', 'max:500'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'thumbnail_file' => ['nullable', 'image', 'max:15360'],
            'status' => ['required', 'in:draft,published'],
        ]);
        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['title']);

        $duplicate = $church->sermons()->where('slug', $validated['slug'])->when($sermon, fn ($query) => $query->whereKeyNot($sermon->id))->exists();
        abort_if($duplicate, 422, 'A sermon with this URL already exists.');

        return $validated;
    }
}
