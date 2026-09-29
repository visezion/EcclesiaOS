<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\User;
use App\Models\WebsiteSubmission;
use App\Support\WebsiteForms;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class WebsiteFormController extends Controller
{
    public function show(Church $church, string $type)
    {
        $form = $this->publicForm($church, $type);

        return view('website.forms.show', compact('church', 'type', 'form'));
    }

    public function store(Request $request, Church $church, string $type)
    {
        if ($request->has('widget_id')) {
            $identity = $request->validate(['widget_id' => ['required', 'string', 'max:180'], 'section_id' => ['required', 'string', 'max:180']]);
            abort_unless(data_get($church->settings, 'website.enabled', true), 404);
            $section = collect(data_get($church->settings, 'website.custom_sections', []))->firstWhere('id', $identity['section_id']);
            abort_unless($section && $church->websitePages()->whereIn('slug', $section['page_slugs'] ?? ['home'])->where('status', 'published')->exists(), 404);
            $widget = WebsiteForms::findWidget($section['components'] ?? [], $identity['widget_id']);
            abort_unless($widget && ($widget['form_type'] ?? 'contact') === $type, 404);
            $form = WebsiteForms::forWidget($church, $widget);
            abort_unless($form && $form['enabled'], 404);
        } else {
            $form = $this->publicForm($church, $type);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [$form['require_email'] ? 'required' : 'nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:60'],
            'message' => ['required', 'string', 'max:10000'],
            'website_url' => ['nullable', 'string', 'max:0'],
        ]);
        $assignee = $form['action'] === 'assign'
            ? User::query()->where('church_id', $church->id)->whereKey($form['assigned_to'])->value('id') : null;
        WebsiteSubmission::create([
            ...collect($data)->only(['name', 'email', 'phone', 'message'])->all(),
            'church_id' => $church->id, 'type' => $type,
            'assigned_to' => $assignee, 'status' => 'new',
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $form['success_message']], 201);
        }

        if ($request->has('widget_id')) {
            return back()->with('widget_success', ['id' => $request->input('widget_id'), 'section' => $request->input('section_id'), 'message' => $form['success_message']]);
        }

        return to_route('website.forms.show', ['church' => $church->slug, 'type' => $type])
            ->with('form_success', $form['success_message']);
    }

    public function index(Request $request)
    {
        $church = $this->adminChurch($request);
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(WebsiteForms::TYPES))],
            'status' => ['nullable', Rule::in(array_keys(WebsiteSubmission::STATUSES))],
        ]);
        $submissions = WebsiteSubmission::query()->where('church_id', $church->id)
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(15)->withQueryString();

        return view('website-studio.forms', [
            'church' => $church, 'forms' => WebsiteForms::forChurch($church),
            'types' => WebsiteForms::TYPES, 'statuses' => WebsiteSubmission::STATUSES,
            'users' => User::query()->where('church_id', $church->id)->orderBy('name')->get(),
            'submissions' => $submissions,
            'breadcrumbs' => [['label' => 'Website Studio', 'url' => route('website-studio.index')], ['label' => 'Forms & Submissions', 'url' => null]],
        ]);
    }

    public function settings(Request $request, string $type)
    {
        $church = $this->adminChurch($request);
        abort_unless(isset(WebsiteForms::TYPES[$type]), 404);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'message_label' => ['required', 'string', 'max:120'],
            'submit_label' => ['required', 'string', 'max:80'],
            'success_message' => ['required', 'string', 'max:500'],
            'require_email' => ['required', 'boolean'],
            'action' => ['required', Rule::in(['inbox', 'assign'])],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'style' => ['required', Rule::in(['rounded', 'square'])],
            'spacing' => ['required', Rule::in(['compact', 'spacious'])],
            'assigned_to' => ['nullable', 'required_if:action,assign', Rule::exists('users', 'id')->where('church_id', $church->id)],
        ]);
        if ($data['action'] === 'inbox') {
            $data['assigned_to'] = null;
        }
        $settings = $church->settings ?? [];
        $settings['website_forms'][$type] = $data;
        $church->update(['settings' => $settings]);

        return back()->with('status', 'Form settings saved.');
    }

    public function update(Request $request, WebsiteSubmission $submission)
    {
        $church = $this->adminChurch($request);
        abort_unless($submission->church_id === $church->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(WebsiteSubmission::STATUSES))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('church_id', $church->id)],
            'private_notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $submission->update($data);

        return back()->with('status', 'Submission updated.');
    }

    private function publicForm(Church $church, string $type): array
    {
        abort_unless((bool) data_get($church->settings, 'website.enabled', true), 404);
        $form = WebsiteForms::forChurch($church)[$type] ?? null;
        abort_unless($form && $form['enabled'], 404);

        return $form;
    }

    private function adminChurch(Request $request): Church
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || $user?->hasPermission('manage studio'), 403);

        return $user->church_id
            ? Church::query()->findOrFail($user->church_id)
            : Church::query()->where('slug', 'kingdom-life-global-church')->firstOrFail();
    }
}
