<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BookstoreProduct;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Event;
use App\Models\Ministry;
use App\Models\Sermon;
use App\Models\WebsitePage;
use App\Services\WebsiteStarterContent;
use App\Support\ModuleRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ChurchWebsiteController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $settings = $this->websiteSettings($church);
        $this->ensureStarterPages($church, $settings);
        $homepage = $this->ensureHomepage($church, $settings);

        return view('website-studio.index', [
            'church' => $church,
            'settings' => $settings,
            'media' => collect($settings['media_library'] ?? [])->sortByDesc('uploaded_at')->values(),
            'homepage' => $homepage,
            'pages' => $church->websitePages()->latest('updated_at')->get(),
            'templates' => $this->templates(),
            'sectionTypes' => $this->sectionTypes(),
            'publicUrl' => route('website.public', ['church' => $church->slug]),
            'previewUrl' => route('website-studio.preview', $homepage),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Website Studio', 'url' => null],
            ],
        ]);
    }

    public function navigation(Request $request): View
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $settings = $this->websiteSettings($church);

        return view('website-studio.navigation', [
            'church' => $church,
            'settings' => $settings,
            'navigation' => is_array($settings['navigation'] ?? null)
                ? collect($settings['navigation'])->values()->all()
                : $this->websiteNavigation($church),
            'menuStyles' => $this->menuStyles(),
            'publicUrl' => route('website.public', ['church' => $church->slug]),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Website Studio', 'url' => route('website-studio.index')],
                ['label' => 'Navigation Builder', 'url' => null],
            ],
        ]);
    }

    public function updateNavigation(Request $request): RedirectResponse
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $validated = $request->validate([
            'menu_style' => ['required', Rule::in(array_keys($this->menuStyles()))],
            'navigation' => ['nullable', 'array', 'max:8'],
            'navigation.*.label' => ['required', 'string', 'max:60'],
            'navigation.*.url' => ['required', 'string', 'max:500', 'regex:/^(#|\/|https?:\/\/)/i'],
            'navigation.*.visible' => ['nullable', 'boolean'],
            'navigation.*.type' => ['nullable', Rule::in(['link', 'dropdown', 'mega'])],
            'navigation.*.children' => ['nullable', 'array', 'max:16'],
            'navigation.*.children.*.label' => ['required', 'string', 'max:60'],
            'navigation.*.children.*.url' => ['required', 'string', 'max:500', 'regex:/^(#|\/|https?:\/\/)/i'],
            'navigation.*.children.*.visible' => ['nullable', 'boolean'],
            'navigation.*.children.*.description' => ['nullable', 'string', 'max:120'],
            'navigation.*.children.*.column' => ['nullable', 'integer', 'between:1,4'],
        ]);

        $settings = $this->websiteSettings($church);
        $settings['menu_style'] = $validated['menu_style'];
        $settings['navigation'] = $this->normalizeNavigationItems($validated['navigation'] ?? []);

        $church->forceFill(['settings' => array_merge($church->settings ?? [], ['website' => $settings])])->save();

        return back()->with('status', 'Website navigation saved.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'template' => ['required', Rule::in(array_keys($this->templates()))],
            'site_name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'hero_image_url' => ['nullable', 'string', 'max:500'],
            'hero_video_url' => ['nullable', 'string', 'max:500'],
            'hero_slides' => ['nullable', 'array', 'max:12'],
            'hero_slides.*' => ['array'],
            'hero_slides.*.type' => ['nullable', Rule::in(['image', 'video'])],
            'hero_slides.*.url' => ['nullable', 'string', 'max:500'],
            'hero_slides.*.poster' => ['nullable', 'string', 'max:500'],
            'hero_slides_configured' => ['nullable', 'boolean'],
            'hero_slide_files' => ['nullable', 'array', 'max:12'],
            'hero_slide_files.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,ogg', 'max:51200'],
            'hero_slide_poster_files' => ['nullable', 'array', 'max:12'],
            'hero_slide_poster_files.*' => ['nullable', 'image', 'max:15360'],
            'logo_file' => ['nullable', 'image', 'max:10240'],
            'hero_image_file' => ['nullable', 'image', 'max:15360'],
            'hero_video_file' => ['nullable', 'mimetypes:video/mp4,video/webm,video/ogg', 'max:51200'],
            'navigation_configured' => ['nullable', 'boolean'],
            'navigation' => ['nullable', 'array', 'max:8'],
            'navigation.*.label' => ['required', 'string', 'max:60'],
            'navigation.*.url' => ['required', 'string', 'max:500', 'regex:/^(#|\\/|https?:\\/\\/)/i'],
            'navigation.*.visible' => ['nullable', 'boolean'],
            'navigation.*.type' => ['nullable', Rule::in(['link', 'dropdown', 'mega'])],
            'navigation.*.children' => ['nullable', 'array', 'max:16'],
            'navigation.*.children.*.label' => ['required', 'string', 'max:60'],
            'navigation.*.children.*.url' => ['required', 'string', 'max:500', 'regex:/^(#|\\/|https?:\\/\\/)/i'],
            'navigation.*.children.*.visible' => ['nullable', 'boolean'],
            'navigation.*.children.*.description' => ['nullable', 'string', 'max:120'],
            'navigation.*.children.*.column' => ['nullable', 'integer', 'between:1,4'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_scheme' => ['nullable', 'in:dark,light'],
            'menu_style' => ['nullable', Rule::in(array_keys($this->menuStyles()))],
            'font' => ['required', 'in:Inter,Manrope,DM Sans,Playfair Display'],
            'hero_eyebrow' => ['nullable', 'string', 'max:100'],
            'hero_heading' => ['required', 'string', 'max:180'],
            'hero_body' => ['nullable', 'string', 'max:1000'],
            'hero_button_label' => ['nullable', 'string', 'max:60'],
            'hero_button_url' => ['nullable', 'string', 'max:300'],
            'welcome_heading' => ['required', 'string', 'max:180'],
            'welcome_body' => ['required', 'string', 'max:2000'],
            'experience_heading' => ['nullable', 'string', 'max:180'],
            'experience_body' => ['nullable', 'string', 'max:1000'],
            'sermon_next_step_enabled' => ['nullable', 'boolean'],
            'sermon_next_step_kicker' => ['nullable', 'string', 'max:80'],
            'sermon_next_step_heading' => ['nullable', 'string', 'max:180'],
            'sermon_next_step_body' => ['nullable', 'string', 'max:1000'],
            'sermon_next_step_one_icon' => ['nullable', 'string', 'max:10'],
            'sermon_next_step_one_title' => ['nullable', 'string', 'max:100'],
            'sermon_next_step_one_body' => ['nullable', 'string', 'max:300'],
            'sermon_next_step_one_link_label' => ['nullable', 'string', 'max:80'],
            'sermon_next_step_one_link' => ['nullable', 'string', 'max:300'],
            'sermon_next_step_two_icon' => ['nullable', 'string', 'max:10'],
            'sermon_next_step_two_title' => ['nullable', 'string', 'max:100'],
            'sermon_next_step_two_body' => ['nullable', 'string', 'max:300'],
            'sermon_next_step_two_link_label' => ['nullable', 'string', 'max:80'],
            'sermon_next_step_two_link' => ['nullable', 'string', 'max:300'],
            'sermon_next_step_three_icon' => ['nullable', 'string', 'max:10'],
            'sermon_next_step_three_title' => ['nullable', 'string', 'max:100'],
            'sermon_next_step_three_body' => ['nullable', 'string', 'max:300'],
            'sermon_next_step_three_link_label' => ['nullable', 'string', 'max:80'],
            'sermon_next_step_three_link' => ['nullable', 'string', 'max:300'],
            'service_kicker' => ['nullable', 'string', 'max:80'],
            'service_heading' => ['nullable', 'string', 'max:180'],
            'service_body' => ['nullable', 'string', 'max:1000'],
            'service_one_title' => ['nullable', 'string', 'max:100'],
            'service_one_body' => ['nullable', 'string', 'max:300'],
            'service_two_title' => ['nullable', 'string', 'max:100'],
            'service_two_body' => ['nullable', 'string', 'max:300'],
            'service_three_title' => ['nullable', 'string', 'max:100'],
            'service_three_body' => ['nullable', 'string', 'max:300'],
            'experience_kicker' => ['nullable', 'string', 'max:80'],
            'experience_one_title' => ['nullable', 'string', 'max:100'],
            'experience_one_body' => ['nullable', 'string', 'max:300'],
            'experience_two_title' => ['nullable', 'string', 'max:100'],
            'experience_two_body' => ['nullable', 'string', 'max:300'],
            'experience_three_title' => ['nullable', 'string', 'max:100'],
            'experience_three_body' => ['nullable', 'string', 'max:300'],
            'experience_four_title' => ['nullable', 'string', 'max:100'],
            'experience_four_body' => ['nullable', 'string', 'max:300'],
            'giving_kicker' => ['nullable', 'string', 'max:80'],
            'giving_heading' => ['nullable', 'string', 'max:180'],
            'giving_body' => ['nullable', 'string', 'max:1000'],
            'giving_button_label' => ['nullable', 'string', 'max:80'],
            'giving_button_url' => ['nullable', 'string', 'max:300'],
            'contact_kicker' => ['nullable', 'string', 'max:80'],
            'contact_heading' => ['nullable', 'string', 'max:180'],
            'footer_text' => ['nullable', 'string', 'max:180'],
            'hero_secondary_label' => ['nullable', 'string', 'max:80'],
            'experience_one_link' => ['nullable', 'string', 'max:80'],
            'experience_two_link' => ['nullable', 'string', 'max:80'],
            'experience_three_link' => ['nullable', 'string', 'max:80'],
            'experience_four_link' => ['nullable', 'string', 'max:80'],
            'event_kicker' => ['nullable', 'string', 'max:80'],
            'event_heading' => ['nullable', 'string', 'max:180'],
            'event_link_label' => ['nullable', 'string', 'max:80'],
            'ministry_kicker' => ['nullable', 'string', 'max:80'],
            'ministry_heading' => ['nullable', 'string', 'max:180'],
            'location_kicker' => ['nullable', 'string', 'max:80'],
            'location_heading' => ['nullable', 'string', 'max:180'],
            'location_body' => ['nullable', 'string', 'max:500'],
            'sermon_kicker' => ['nullable', 'string', 'max:80'],
            'sermon_heading' => ['nullable', 'string', 'max:180'],
            'store_kicker' => ['nullable', 'string', 'max:80'],
            'store_heading' => ['nullable', 'string', 'max:180'],
            'store_body' => ['nullable', 'string', 'max:500'],
            'seo_description' => ['nullable', 'string', 'max:180'],
            'contact_email' => ['nullable', 'email', 'max:180'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'contact_address' => ['nullable', 'string', 'max:500'],
        ]);

        $settings = array_merge($this->websiteSettings($church), $validated, [
            'enabled' => $request->boolean('enabled'),
        ]);
        if ($request->boolean('hero_slides_configured')) {
            $submittedSlides = $validated['hero_slides'] ?? [];
            foreach ($submittedSlides as $index => &$slide) {
                if (! is_array($slide)) {
                    continue;
                }
                $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($slide['key'] ?? $index));
                $mediaFile = $request->file('hero_slide_files.'.$key);
                if ($mediaFile instanceof UploadedFile) {
                    $slide['url'] = $this->storeWebsiteAsset($mediaFile, $church);
                    $slide['type'] = str_starts_with((string) $mediaFile->getMimeType(), 'video/') ? 'video' : 'image';
                }
                $posterFile = $request->file('hero_slide_poster_files.'.$key);
                if ($posterFile instanceof UploadedFile) {
                    $slide['poster'] = $this->storeWebsiteAsset($posterFile, $church);
                }
            }
            unset($slide);
            $settings['hero_slides'] = $this->normalizeHeroSlides($submittedSlides);
        }
        unset($settings['hero_slide_files'], $settings['hero_slide_poster_files']);
        if ($request->boolean('navigation_configured')) {
            $settings['navigation'] = $this->normalizeNavigationItems($validated['navigation'] ?? []);
        }
        unset($settings['navigation_configured']);
        unset($settings['landing_page_enabled']);

        foreach (['logo_file' => 'logo_url', 'hero_image_file' => 'hero_image_url', 'hero_video_file' => 'hero_video_url'] as $fileKey => $settingKey) {
            if ($request->hasFile($fileKey)) {
                $settings[$settingKey] = $this->storeWebsiteAsset($request->file($fileKey), $church);
            }
        }
        $settings['media_library'] = $this->websiteSettings($church)['media_library'] ?? [];
        $church->forceFill(['settings' => array_merge($church->settings ?? [], ['website' => $settings])])->save();
        $this->ensureStarterPages($church, $settings);

        return back()->with('status', 'Website settings saved.');
    }

    public function storePage(Request $request): RedirectResponse
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $validated = $this->validatedPage($request, $church);
        $validated['church_id'] = $church->id;
        $validated['published_at'] = $validated['status'] === 'published' ? now() : null;

        WebsitePage::query()->create($validated);

        $page = WebsitePage::query()->where('church_id', $church->id)->where('slug', $validated['slug'])->firstOrFail();

        return redirect()->route('website-studio.pages.edit', $page)->with('status', 'Website page created.');
    }

    public function editPage(Request $request, WebsitePage $page): View
    {
        $this->authorizeStudio($request);
        $this->authorizePage($request, $page);

        return view('website-studio.page-edit', [
            'church' => $page->church,
            'page' => $page,
            'settings' => $this->websiteSettings($page->church),
            'media' => collect($this->websiteSettings($page->church)['media_library'] ?? [])->sortByDesc('uploaded_at')->values(),
            'customSections' => collect($this->websiteSettings($page->church)['custom_sections'] ?? [])->sortBy('order')->values(),
            'templates' => $this->templates(),
            'sectionTypes' => $this->sectionTypes(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Website Studio', 'url' => route('website-studio.index')],
                ['label' => 'Design '.$page->title, 'url' => null],
            ],
        ]);
    }

    public function sections(Request $request): View
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $settings = $this->websiteSettings($church);

        return view('website-studio.sections', [
            'church' => $church,
            'sections' => collect($settings['custom_sections'] ?? [])->sortBy('order')->values(),
            'media' => collect($settings['media_library'] ?? [])->sortByDesc('uploaded_at')->values(),
            'pages' => $church->websitePages()->orderBy('title')->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Website Studio', 'url' => route('website-studio.index')],
                ['label' => 'Reusable sections', 'url' => null],
            ],
        ]);
    }

    public function mediaLibrary(Request $request): View
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $settings = $this->websiteSettings($church);

        return view('website-studio.media-library', [
            'church' => $church,
            'media' => collect($settings['media_library'] ?? [])->sortByDesc('uploaded_at')->values(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Website Studio', 'url' => route('website-studio.index')],
                ['label' => 'Media library', 'url' => null],
            ],
        ]);
    }

    public function uploadMedia(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $request->validate(['media' => ['required', 'image', 'max:15360']]);
        $this->storeWebsiteAsset($request->file('media'), $church);

        if ($request->expectsJson()) {
            $record = collect($this->websiteSettings($church)['media_library'] ?? [])->last();

            return response()->json(['media' => $record]);
        }

        return back()->with('status', 'Image added to the media library.');
    }

    public function deleteMedia(Request $request, string $media): RedirectResponse
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $settings = $this->websiteSettings($church);
        $record = collect($settings['media_library'] ?? [])->first(fn (array $item): bool => ($item['id'] ?? null) === $media);
        abort_unless($record !== null, 404);
        $usageSettings = $settings;
        unset($usageSettings['media_library']);
        abort_if(Str::contains(json_encode($usageSettings), (string) ($record['path'] ?? '')), 422, 'This image is still used by the website.');
        Storage::disk('public')->delete((string) $record['path']);
        $settings['media_library'] = collect($settings['media_library'] ?? [])->reject(fn (array $item): bool => ($item['id'] ?? null) === $media)->values()->all();
        $this->saveWebsiteSettings($church, $settings);

        return back()->with('status', 'Image removed from the media library.');
    }

    public function editSection(Request $request, string $section): View
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $settings = $this->websiteSettings($church);
        $record = collect($settings['custom_sections'] ?? [])->first(fn (array $item): bool => ($item['id'] ?? null) === $section);
        abort_unless($record !== null, 404);

        return view('website-studio.section-edit', [
            'church' => $church,
            'section' => $record,
            'pages' => $church->websitePages()->orderBy('title')->get(),
            'media' => collect($this->websiteSettings($church)['media_library'] ?? [])->sortByDesc('uploaded_at')->values(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Website Studio', 'url' => route('website-studio.index')],
                ['label' => 'Reusable sections', 'url' => route('website-studio.sections')],
                ['label' => 'Edit '.$record['title'], 'url' => null],
            ],
        ]);
    }

    public function createSection(Request $request): View
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);

        return view('website-studio.section-create', [
            'church' => $church,
            'pages' => $church->websitePages()->orderBy('title')->get(),
            'media' => collect($this->websiteSettings($church)['media_library'] ?? [])->sortByDesc('uploaded_at')->values(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Website Studio', 'url' => route('website-studio.index')],
                ['label' => 'Reusable sections', 'url' => route('website-studio.sections')],
                ['label' => 'New section', 'url' => null],
            ],
        ]);
    }

    public function storeSection(Request $request): RedirectResponse
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $settings = $this->websiteSettings($church);
        $section = $this->validatedSection($request, $church);
        $section['id'] = (string) Str::uuid();
        $section['order'] = count($settings['custom_sections'] ?? []);
        $settings['custom_sections'][] = $section;
        $settings['media_library'] = $this->websiteSettings($church)['media_library'] ?? [];
        $this->saveWebsiteSettings($church, $settings);

        return redirect()->route('website-studio.sections.edit', $section['id'])->with('status', 'Reusable section created.');
    }

    public function updateSection(Request $request, string $section): RedirectResponse
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $settings = $this->websiteSettings($church);
        $index = collect($settings['custom_sections'] ?? [])->search(fn (array $item): bool => ($item['id'] ?? null) === $section);
        abort_unless($index !== false, 404);
        $updated = $this->validatedSection($request, $church);
        $updated['id'] = $section;
        $updated['order'] = $settings['custom_sections'][$index]['order'] ?? $index;
        $settings['custom_sections'][$index] = $updated;
        $settings['media_library'] = $this->websiteSettings($church)['media_library'] ?? [];
        $this->saveWebsiteSettings($church, $settings);

        return redirect()->route('website-studio.sections.edit', $section)->with('status', 'Reusable section updated.');
    }

    public function destroySection(Request $request, string $section): RedirectResponse
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $settings = $this->websiteSettings($church);
        $settings['custom_sections'] = collect($settings['custom_sections'] ?? [])->reject(fn (array $item): bool => ($item['id'] ?? null) === $section)->values()->all();
        $this->saveWebsiteSettings($church, $settings);

        return back()->with('status', 'Reusable section removed.');
    }

    public function updatePage(Request $request, WebsitePage $page): RedirectResponse
    {
        $this->authorizeStudio($request);
        $this->authorizePage($request, $page);
        $validated = $this->validatedPage($request, $page->church);
        if ($request->hasFile('page_hero_image_file')) {
            $validated['design'] = $validated['design'] ?? [];
            $validated['design']['hero_image_url'] = $this->storeWebsiteAsset($request->file('page_hero_image_file'), $page->church);
        }
        $validated['published_at'] = $validated['status'] === 'published'
            ? ($page->published_at ?? now())
            : null;
        $page->update($validated);
        $this->syncPageCustomSections($request, $page);

        return back()->with('status', 'Website page updated.');
    }

    public function destroyPage(Request $request, WebsitePage $page): RedirectResponse
    {
        $this->authorizeStudio($request);
        $this->authorizePage($request, $page);
        abort_if($page->slug === 'home', 422, 'The homepage cannot be deleted.');
        $page->delete();

        return back()->with('status', 'Website page moved to archive.');
    }

    public function preview(Request $request, WebsitePage $page): View
    {
        $this->authorizeStudio($request);
        $this->authorizePage($request, $page);

        return $this->renderWebsite($page->church, $page, true);
    }

    public function show(Church $church, ?string $page = null): View
    {
        $settings = $this->websiteSettings($church);
        abort_unless((bool) ($settings['enabled'] ?? true), 404);
        $this->ensureStarterPages($church, $settings);

        $slug = $page ?: ($settings['homepage_slug'] ?? 'home');
        $websitePage = $church->websitePages()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if ($websitePage === null && $slug === 'home') {
            $websitePage = $this->ensureHomepage($church, $settings);
        }

        abort_if($websitePage === null, 404);

        return $this->renderWebsite($church, $websitePage);
    }

    public function showEvent(Church $church, Event $event): View
    {
        $settings = $this->websiteSettings($church);
        abort_unless((bool) ($settings['enabled'] ?? true), 404);
        abort_unless($event->church_id === $church->id && $event->show_on_website && in_array($event->status, ['scheduled', 'published'], true), 404);

        $event->load([
            'campus',
            'program',
            'sessions' => fn ($query) => $query->whereIn('status', ['scheduled', 'published'])->with('campus')->orderBy('session_date')->orderBy('starts_at'),
        ]);

        $assetUrl = static function (?string $value): ?string {
            if (! filled($value)) {
                return null;
            }

            $value = trim($value);
            if (str_starts_with($value, 'http') || str_starts_with($value, '//')) {
                return $value;
            }

            $value = ltrim($value, '/');
            if (($storagePosition = strpos($value, 'storage/')) !== false) {
                $value = substr($value, $storagePosition + strlen('storage/'));
            }

            return asset('storage/'.ltrim($value, '/'));
        };

        return view('website.templates.main.event', [
            'church' => $church,
            'settings' => $settings,
            'event' => $event,
            'logoUrl' => $assetUrl($settings['logo_url'] ?? null),
            'posterUrl' => $event->poster_path ? url('storage/'.ltrim($event->poster_path, '/')) : null,
            'navigation' => $this->websiteNavigation($church),
            'relatedEvents' => Event::query()
                ->where('church_id', $church->id)
                ->where('show_on_website', true)
                ->whereIn('status', ['scheduled', 'published'])
                ->whereKeyNot($event->getKey())
                ->where('starts_at', '>', now())
                ->orderBy('starts_at')
                ->limit(3)
                ->get(),
        ]);
    }

    public function showSermon(Church $church, Sermon $sermon): View
    {
        $settings = $this->websiteSettings($church);
        abort_unless((bool) ($settings['enabled'] ?? true), 404);
        abort_unless($sermon->church_id === $church->id && $sermon->status === 'published', 404);

        return view('website.templates.main.sermon', [
            'church' => $church,
            'settings' => $settings,
            'sermon' => $sermon,
            'relatedSermons' => Sermon::query()
                ->where('church_id', $church->id)
                ->where('status', 'published')
                ->whereKeyNot($sermon->getKey())
                ->latest('preached_at')
                ->latest('id')
                ->limit(6)
                ->get(),
            'navigation' => $this->websiteNavigation($church),
        ]);
    }

    private function renderWebsite(Church $church, WebsitePage $page, bool $preview = false): View
    {
        $settings = array_merge(
            $this->websiteSettings($church),
            collect($page->design ?? [])->filter(fn ($value): bool => filled($value))->all(),
        );
        $template = $settings['template'] ?? 'main';
        $templateView = view()->exists('website.templates.'.$template.'.index')
            ? 'website.templates.'.$template.'.index'
            : 'website.templates.main.index';
        $availableCustomSections = collect($settings['custom_sections'] ?? [])->filter(fn (array $section): bool => in_array($page->slug, $section['page_slugs'] ?? ['home'], true));
        $pageSectionOrder = collect($page->sections ?? [])
            ->map(fn ($section) => is_array($section) ? ($section['type'] ?? null) : $section)
            ->filter()
            ->map(fn ($section): string => (string) $section)
            ->values()
            ->all();
        $pageSectionOrder = array_values(array_unique(array_merge(
            $pageSectionOrder,
            $availableCustomSections->pluck('id')->map(fn ($id): string => (string) $id)->all(),
        )));

        $websiteModuleEnabled = ! ModuleRegistry::isDisabledRoute('website-studio.index', $church);

        return view($templateView, [
            'church' => $church,
            'page' => $page,
            'settings' => $settings,
            'preview' => $preview,
            'events' => $websiteModuleEnabled
                ? Event::query()->where('church_id', $church->id)->where('show_on_website', true)->whereIn('status', ['scheduled', 'published'])->where('starts_at', '>', now())->orderBy('starts_at')->get()
                : collect(),
            'ministries' => Ministry::query()->where('church_id', $church->id)->where('status', 'active')->orderBy('name')->limit(6)->get(),
            'campuses' => Campus::query()->where('church_id', $church->id)->where('status', 'active')->orderBy('name')->limit(8)->get(),
            'sermons' => Sermon::query()->where('church_id', $church->id)->where('status', 'published')->latest('preached_at')->latest('id')->get(),
            'products' => BookstoreProduct::query()->where('church_id', $church->id)->where('status', 'active')->where('stock_quantity', '>', 0)->orderBy('name')->limit(8)->get(),
            'navigation' => $this->websiteNavigation($church),
            'pageSectionOrder' => $pageSectionOrder,
            'customSections' => collect($settings['custom_sections'] ?? [])->filter(fn (array $section): bool => in_array($page->slug, $section['page_slugs'] ?? ['home'], true) && empty($section['components']))->sortBy('order')->values(),
            'customComponentSections' => collect($settings['custom_sections'] ?? [])->filter(fn (array $section): bool => in_array($page->slug, $section['page_slugs'] ?? ['home'], true) && ! empty($section['components']) && array_is_list($section['components']))->sortBy('order')->values(),
            'customNestedSections' => collect($settings['custom_sections'] ?? [])->filter(fn (array $section): bool => in_array($page->slug, $section['page_slugs'] ?? ['home'], true) && (($section['components']['type'] ?? null) === 'columns'))->sortBy('order')->values(),
        ]);
    }

    private function authorizeStudio(Request $request): void
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || $user?->hasPermission('manage studio'), 403);
    }

    private function authorizePage(Request $request, WebsitePage $page): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $page->church_id === $request->user()?->church_id, 404);
    }

    private function studioChurch(Request $request): Church
    {
        $church = $request->user()?->church_id
            ? Church::query()->find($request->user()->church_id)
            : null;

        return $church ?? Church::query()->firstOrCreate(
            ['slug' => 'kingdom-life-global-church'],
            [
                'name' => config('church.name'),
                'timezone' => config('church.timezone'),
                'currency' => config('church.currency'),
                'email' => config('church.contact_email'),
                'phone' => config('church.contact_phone'),
                'address' => config('church.address'),
                'settings' => [],
            ],
        );
    }

    /** @return array<string, mixed> */
    private function websiteSettings(Church $church): array
    {
        $globalSettings = is_array($church->settings) ? $church->settings : [];

        $settings = array_merge([
            'enabled' => true,
            'template' => 'main',
            'site_name' => data_get($globalSettings, 'church_name') ?: $church->name,
            'tagline' => 'A place to belong, become, and believe.',
            'logo_url' => data_get($globalSettings, 'logo'),
            'favicon_url' => data_get($globalSettings, 'favicon'),
            'hero_image_url' => null,
            'hero_video_url' => null,
            'hero_slides' => [],
            'primary_color' => '#4338CA',
            'accent_color' => '#F59E0B',
            'color_scheme' => 'dark',
            'menu_style' => 'classic',
            'font' => 'Manrope',
            'hero_eyebrow' => 'You are welcome here',
            'hero_heading' => 'Find hope. Find community. Find your next step.',
            'hero_body' => 'Join us as we worship Jesus, care for one another, and serve our city together.',
            'hero_secondary_label' => 'Meet the community',
            'hero_button_label' => 'Plan your visit',
            'hero_button_url' => '#visit',
            'welcome_heading' => 'A church family for every season of life.',
            'welcome_body' => 'We are a growing family of people learning to follow Jesus with courage, compassion, and joy. There is a place for you here.',
            'experience_heading' => 'Find the right experience for you.',
            'experience_body' => 'No matter where you are, online or in person, become part of all God is doing.',
            'sermon_next_step_enabled' => true,
            'sermon_next_step_kicker' => 'Your next step',
            'sermon_next_step_heading' => 'Take your next step of faith',
            'sermon_next_step_body' => 'Everyone’s at a different point in their faith journey. Wherever you are, we’re here to help you take the next step.',
            'sermon_next_step_one_icon' => '♡',
            'sermon_next_step_one_title' => 'Make a decision for Christ',
            'sermon_next_step_one_body' => 'If you haven’t yet accepted Jesus as your Savior, we want to help you discover the new life He has to offer.',
            'sermon_next_step_one_link_label' => 'Learn more',
            'sermon_next_step_one_link' => '#contact',
            'sermon_next_step_two_icon' => '≋',
            'sermon_next_step_two_title' => 'Go public with your faith',
            'sermon_next_step_two_body' => 'Baptism is a public declaration of your decision to follow Christ. Discover why baptism is an important step of faith.',
            'sermon_next_step_two_link_label' => 'Learn more',
            'sermon_next_step_two_link' => '#contact',
            'sermon_next_step_three_icon' => '♧',
            'sermon_next_step_three_title' => 'Find your people',
            'sermon_next_step_three_body' => 'We don’t want you to attend church alone. Connect with others at a physical campus, Pop-Up, Watch Party, or online.',
            'sermon_next_step_three_link_label' => 'Learn more',
            'sermon_next_step_three_link' => '#contact',
            'service_kicker' => 'Gather with us',
            'service_heading' => 'There is a place for you this Sunday.',
            'service_body' => 'Come early for coffee, stay after for conversation, and worship with a community that wants to know your name.',
            'service_one_title' => 'Sunday worship',
            'service_one_body' => 'Every Sunday · 9:00 AM & 11:00 AM',
            'service_two_title' => 'Midweek community',
            'service_two_body' => 'Wednesday · 6:30 PM',
            'service_three_title' => 'Kids & students',
            'service_three_body' => 'Safe, joyful spaces for every age.',
            'experience_kicker' => 'Find your place',
            'experience_one_title' => 'Physical campus',
            'experience_one_body' => 'Worship with us in person at one of our locations.',
            'experience_two_title' => 'Live streams',
            'experience_two_body' => 'Join our online community wherever you are.',
            'experience_three_title' => 'Community',
            'experience_three_body' => 'Find people to grow with and a place to serve.',
            'experience_four_title' => 'Next step',
            'experience_four_body' => 'Ask a question, plan a visit, or get connected.',
            'experience_one_link' => 'Find a location →',
            'experience_two_link' => 'Find a time →',
            'experience_three_link' => 'Find your people →',
            'experience_four_link' => 'Get connected →',
            'event_kicker' => 'Coming up',
            'event_heading' => 'Make room for what matters.',
            'event_link_label' => 'See all events ↗',
            'ministry_kicker' => 'Life together',
            'ministry_heading' => 'Find your people. Grow together.',
            'location_kicker' => 'Our locations',
            'location_heading' => 'Gather where you are.',
            'location_body' => 'Visit one of our campuses and find a church family near you.',
            'sermon_kicker' => 'Watch and listen',
            'sermon_heading' => 'Messages for the journey.',
            'store_kicker' => 'Church store',
            'store_heading' => 'Resources for your next step.',
            'store_body' => 'These products are connected directly to the church bookstore catalog.',
            'giving_kicker' => 'Make an impact',
            'giving_heading' => 'Generosity changes lives.',
            'giving_body' => 'Your gifts help us care for people, strengthen families, and bring hope beyond our walls.',
            'giving_button_label' => 'Give online',
            'giving_button_url' => '/give',
            'contact_kicker' => 'Plan your visit',
            'contact_heading' => 'We would love to meet you.',
            'footer_text' => 'Church management, beautifully connected.',
            'seo_description' => null,
            'contact_email' => $church->email,
            'contact_phone' => $church->phone,
            'contact_address' => $church->address,
            'homepage_slug' => 'home',
            'custom_sections' => [],
            'media_library' => [],
        ], data_get($church->settings, 'website', []));

        // Setup branding is the public fallback; Website Studio values may still
        // override it when a church has configured a dedicated website asset.
        $settings['site_name'] = (string) (data_get($globalSettings, 'church_name') ?: $settings['site_name']);
        $settings['logo_url'] = $settings['logo_url'] ?: data_get($globalSettings, 'logo');
        $settings['favicon_url'] = $settings['favicon_url'] ?: data_get($globalSettings, 'favicon');

        // Only the main public template is production-ready. Normalize legacy
        // experimental template values so older churches keep rendering safely.
        $settings['template'] = 'main';

        return $settings;
    }

    /** @return list<array{type: string, url: string, poster: string}> */
    private function normalizeHeroSlides(mixed $slides): array
    {
        if (is_string($slides)) {
            $slides = json_decode($slides, true);
        }

        return collect(is_array($slides) ? $slides : [])->filter(fn ($slide): bool => is_array($slide))
            ->map(fn (array $slide): array => [
                'type' => ($slide['type'] ?? 'image') === 'video' ? 'video' : 'image',
                'url' => trim((string) ($slide['url'] ?? '')),
                'poster' => trim((string) ($slide['poster'] ?? '')),
            ])->filter(fn (array $slide): bool => $slide['url'] !== '')
            ->take(12)->values()->all();
    }

    /** @return array<string, mixed> */
    private function validatedSection(Request $request, Church $church): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'eyebrow' => ['nullable', 'string', 'max:100'],
            'body' => ['nullable', 'string', 'max:3000'],
            'button_label' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'image_file' => ['nullable', 'image', 'max:15360'],
            'video_file' => ['nullable', 'mimetypes:video/mp4,video/webm,video/ogg', 'max:51200'],
            'background_image_file' => ['nullable', 'image', 'max:15360'],
            'background_video_file' => ['nullable', 'mimetypes:video/mp4,video/webm,video/ogg', 'max:51200'],
            'page_slugs' => ['nullable', 'array'],
            'page_slugs.*' => ['string', 'alpha_dash', 'max:100'],
            'components' => ['nullable', 'string', 'max:50000'],
            'column_widths' => ['nullable', 'array'],
            'column_widths.*' => ['integer', 'min:5', 'max:95'],
            'component_files' => ['nullable', 'array'],
            'component_files.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,ogg', 'max:51200'],
            'component_image_files' => ['nullable', 'array'],
            'component_image_files.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp', 'max:15360'],
            'component_video_files' => ['nullable', 'array'],
            'component_video_files.*' => ['file', 'mimes:mp4,webm,ogg', 'max:51200'],
            'remove_column_background_images' => ['nullable', 'array'],
            'remove_column_background_images.*' => ['string', 'max:80'],
            'remove_column_background_videos' => ['nullable', 'array'],
            'remove_column_background_videos.*' => ['string', 'max:80'],
        ]);
        $data['page_slugs'] = array_values($data['page_slugs'] ?? ['home']);
        $data['components'] = $this->normalizeSectionComponents($data['components'] ?? null);
        $columnCount = max(1, min(4, ((int) collect($data['components'])->max('column')) + 1));
        $widths = array_values(array_map('intval', $data['column_widths'] ?? []));
        $data['column_widths'] = count($widths) === $columnCount
            ? array_map(fn (int $width): int => max(5, min(95, $width)), $widths)
            : array_fill(0, $columnCount, 1);
        $this->storeComponentFiles(
            $data['components'],
            $request->file('component_files', []),
            $request->file('component_image_files', []),
            $request->file('component_video_files', []),
            $church,
        );
        $this->removeColumnBackgrounds(
            $data['components'],
            array_keys($data['remove_column_background_images'] ?? []),
            array_keys($data['remove_column_background_videos'] ?? []),
        );
        if ($request->hasFile('image_file')) {
            $data['image_url'] = $this->storeWebsiteAsset($request->file('image_file'), $church);
        }
        if ($request->hasFile('video_file')) {
            $data['video_url'] = $this->storeWebsiteAsset($request->file('video_file'), $church);
        }
        unset($data['image_file'], $data['video_file'], $data['background_image_file'], $data['background_video_file'], $data['component_files'], $data['component_image_files'], $data['component_video_files'], $data['remove_column_background_images'], $data['remove_column_background_videos']);

        return $data;
    }

    /** @return array<string, mixed>|list<array<string, mixed>> */
    private function normalizeSectionComponents(?string $encoded): array
    {
        $components = json_decode($encoded ?: '[]', true);
        if (! is_array($components)) {
            return [];
        }

        if (($components['type'] ?? null) === 'columns') {
            return $this->normalizeColumnNode($components);
        }

        return collect($components)->filter(fn ($component): bool => is_array($component))->map(function (array $component): array {
            $type = in_array($component['type'] ?? null, ['heading', 'text', 'quote', 'image', 'video', 'button', 'spacer', 'carousel', 'video-slider', 'gallery', 'card', 'icon', 'divider', 'events', 'sermons'], true)
                ? $component['type']
                : 'text';

            return [
                'id' => (string) ($component['id'] ?? Str::uuid()),
                'type' => $type,
                'text' => Str::limit((string) ($component['text'] ?? ''), 5000, ''),
                'url' => Str::limit((string) ($component['url'] ?? ''), 500, ''),
                'alt' => Str::limit((string) ($component['alt'] ?? ''), 180, ''),
                'slides' => $type === 'carousel' ? $this->normalizeCarouselSlides($component['slides'] ?? []) : ($type === 'video-slider' ? $this->normalizeVideoSlides($component['slides'] ?? []) : []),
                'autoplay' => in_array($type, ['carousel', 'video-slider'], true) ? ($component['autoplay'] ?? true) !== false : false,
                'video_slider_height' => $type === 'video-slider' && in_array((int) ($component['video_slider_height'] ?? 420), [300, 420, 560, 700], true) ? (int) $component['video_slider_height'] : 0,
                'height' => $type === 'spacer' ? max(0, min(600, (int) ($component['height'] ?? 36))) : 0,
                'title' => in_array($type, ['card', 'icon', 'gallery'], true) ? Str::limit((string) ($component['title'] ?? ''), 180, '') : '',
                'body' => in_array($type, ['card', 'icon'], true) ? Str::limit((string) ($component['body'] ?? ''), 1000, '') : '',
                'background_color' => in_array($type, ['card', 'icon'], true) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['background_color'] ?? '')) ? $component['background_color'] : ($type === 'icon' ? '#ede9fe' : '#6d4aff'),
                'background_video' => $type === 'card' ? Str::limit((string) ($component['background_video'] ?? ''), 500, '') : '',
                'card_border_width' => $type === 'card' ? max(0, min(12, (int) ($component['card_border_width'] ?? 0))) : 0,
                'card_border_color' => $type === 'card' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['card_border_color'] ?? '')) ? $component['card_border_color'] : '#ffffff',
                'card_shadow' => $type === 'card' && in_array($component['card_shadow'] ?? null, ['none', 'small', 'medium', 'large'], true) ? $component['card_shadow'] : 'none',
                'align' => in_array($component['align'] ?? null, ['left', 'center', 'right', 'justify'], true) ? $component['align'] : 'left',
                'icon' => $type === 'icon' ? Str::limit((string) ($component['icon'] ?? '✦'), 8, '') : '',
                'icon_color' => $type === 'icon' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['icon_color'] ?? '')) ? $component['icon_color'] : '#6d4aff',
                'icon_size' => $type === 'icon' ? max(24, min(160, (int) ($component['icon_size'] ?? 56))) : 0,
                'link' => in_array($type, ['card', 'icon'], true) ? Str::limit((string) ($component['link'] ?? ''), 500, '') : '',
                'button_color' => $type === 'button' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['button_color'] ?? '')) ? $component['button_color'] : '#6d4aff',
                'button_size' => $type === 'button' && in_array($component['button_size'] ?? null, ['very-small', 'small', 'medium', 'big', 'very-big'], true) ? $component['button_size'] : 'medium',
                'images' => $type === 'gallery' ? collect(is_array($component['images'] ?? null) ? $component['images'] : [])->map(fn ($image): array => ['id' => (string) ($image['id'] ?? Str::uuid()), 'url' => Str::limit((string) ($image['url'] ?? ''), 500, ''), 'alt' => Str::limit((string) ($image['alt'] ?? ''), 180, ''), 'position' => in_array($image['position'] ?? null, ['center', 'top', 'bottom', 'left', 'right'], true) ? $image['position'] : 'center'])->values()->all() : [],
                'style' => $type === 'gallery' && in_array($component['style'] ?? null, ['grid', 'slider', 'masonry', 'featured', 'art-wall'], true) ? $component['style'] : 'grid',
                'columns' => $type === 'gallery' ? max(2, min(6, (int) ($component['columns'] ?? 3))) : 0,
                'divider_style' => $type === 'divider' && in_array($component['divider_style'] ?? null, ['solid', 'dashed', 'dotted'], true) ? $component['divider_style'] : 'solid',
                'divider_color' => $type === 'divider' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['divider_color'] ?? '')) ? $component['divider_color'] : '#e2e8f0',
                'divider_width' => $type === 'divider' ? max(10, min(100, (int) ($component['divider_width'] ?? 100))) : 0,
                'divider_thickness' => $type === 'divider' ? max(1, min(8, (int) ($component['divider_thickness'] ?? 1))) : 0,
                'divider_spacing' => $type === 'divider' ? max(0, min(120, (int) ($component['divider_spacing'] ?? 24))) : 0,
                'event_limit' => $type === 'events' && (($component['event_limit'] ?? 3) === 'all' || in_array((int) ($component['event_limit'] ?? 0), [3, 4, 6, 8], true)) ? (($component['event_limit'] ?? 3) === 'all' ? 'all' : (int) $component['event_limit']) : 3,
                'event_style' => $type === 'events' && in_array($component['event_style'] ?? null, ['list', 'gallery'], true) ? $component['event_style'] : 'list',
                'event_button_color' => $type === 'events' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['event_button_color'] ?? '')) ? $component['event_button_color'] : '#6d4aff',
                'event_button_text_color' => $type === 'events' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['event_button_text_color'] ?? '')) ? $component['event_button_text_color'] : '#ffffff',
                'sermon_limit' => $type === 'sermons' && (($component['sermon_limit'] ?? 'all') === 'all' || in_array((int) ($component['sermon_limit'] ?? 0), [3, 4, 5, 6, 8, 9], true)) ? (($component['sermon_limit'] ?? 'all') === 'all' ? 'all' : (int) $component['sermon_limit']) : 'all',
                'animation' => in_array($component['animation'] ?? null, ['none', 'fade', 'slide-up', 'slide-left', 'zoom', 'bounce', 'float'], true) ? $component['animation'] : 'none',
                'column' => max(0, min(3, (int) ($component['column'] ?? 0))),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function normalizeColumnNode(array $node): array
    {
        if (is_array($node['groups'] ?? null) && $node['groups'] !== []) {
            return [
                'id' => (string) ($node['id'] ?? Str::uuid()),
                'type' => 'columns',
                'groups' => collect($node['groups'])->map(fn ($group): array => $this->normalizeColumnNode(is_array($group) ? $group : []))->values()->all(),
            ];
        }
        $columns = collect($node['columns'] ?? [])->map(function ($column): array {
            $column = is_array($column) ? $column : [];

            return [
                'id' => (string) ($column['id'] ?? Str::uuid()),
                'width' => max(1, min(95, (int) ($column['width'] ?? 1))),
                'background_color' => ($column['background_color'] ?? null) === 'transparent' || filter_var($column['background_transparent'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'transparent' : (preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($column['background_color'] ?? '')) ? $column['background_color'] : 'transparent'),
                'background_transparent' => ($column['background_color'] ?? null) === 'transparent' || filter_var($column['background_transparent'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'background_image' => Str::limit((string) ($column['background_image'] ?? ''), 500, ''),
                'background_video' => Str::limit((string) ($column['background_video'] ?? ''), 500, ''),
                'height' => in_array($column['height'] ?? 'auto', ['auto', 'compact', 'tall', 'full'], true) ? ($column['height'] ?? 'auto') : 'auto',
                'column_width' => in_array($column['column_width'] ?? ($column['content_width'] ?? 'default'), ['default', 'wide', 'full'], true) ? ($column['column_width'] ?? ($column['content_width'] ?? 'default')) : 'default',
                'components' => collect($column['components'] ?? [])->filter(fn ($component): bool => is_array($component))->map(function (array $component): array {
                    if (($component['type'] ?? null) === 'columns') {
                        return $this->normalizeColumnNode($component);
                    }

                    $type = in_array($component['type'] ?? null, ['heading', 'text', 'quote', 'image', 'video', 'button', 'spacer', 'carousel', 'video-slider', 'gallery', 'card', 'icon', 'divider', 'events', 'sermons'], true)
                        ? $component['type']
                        : 'text';

                    return [
                        'id' => (string) ($component['id'] ?? Str::uuid()),
                        'type' => $type,
                        'text' => Str::limit((string) ($component['text'] ?? ''), 5000, ''),
                        'url' => Str::limit((string) ($component['url'] ?? ''), 500, ''),
                        'alt' => Str::limit((string) ($component['alt'] ?? ''), 180, ''),
                        'slides' => $type === 'carousel' ? $this->normalizeCarouselSlides($component['slides'] ?? []) : ($type === 'video-slider' ? $this->normalizeVideoSlides($component['slides'] ?? []) : []),
                        'autoplay' => in_array($type, ['carousel', 'video-slider'], true) ? ($component['autoplay'] ?? true) !== false : false,
                        'video_slider_height' => $type === 'video-slider' && in_array((int) ($component['video_slider_height'] ?? 420), [300, 420, 560, 700], true) ? (int) ($component['video_slider_height'] ?? 420) : 0,
                        'height' => $type === 'spacer' ? max(0, min(600, (int) ($component['height'] ?? 36))) : 0,
                        'title' => in_array($type, ['card', 'icon', 'gallery'], true) ? Str::limit((string) ($component['title'] ?? ''), 180, '') : '',
                        'body' => in_array($type, ['card', 'icon'], true) ? Str::limit((string) ($component['body'] ?? ''), 1000, '') : '',
                        'background_color' => in_array($type, ['card', 'icon'], true) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['background_color'] ?? '')) ? $component['background_color'] : ($type === 'icon' ? '#ede9fe' : '#6d4aff'),
                        'background_video' => $type === 'card' ? Str::limit((string) ($component['background_video'] ?? ''), 500, '') : '',
                        'card_border_width' => $type === 'card' ? max(0, min(12, (int) ($component['card_border_width'] ?? 0))) : 0,
                        'card_border_color' => $type === 'card' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['card_border_color'] ?? '')) ? $component['card_border_color'] : '#ffffff',
                        'card_shadow' => $type === 'card' && in_array($component['card_shadow'] ?? null, ['none', 'small', 'medium', 'large'], true) ? $component['card_shadow'] : 'none',
                        'align' => in_array($component['align'] ?? null, ['left', 'center', 'right', 'justify'], true) ? $component['align'] : 'left',
                        'icon' => $type === 'icon' ? Str::limit((string) ($component['icon'] ?? '✦'), 8, '') : '',
                        'icon_color' => $type === 'icon' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['icon_color'] ?? '')) ? $component['icon_color'] : '#6d4aff',
                        'icon_size' => $type === 'icon' ? max(24, min(160, (int) ($component['icon_size'] ?? 56))) : 0,
                        'link' => in_array($type, ['card', 'icon'], true) ? Str::limit((string) ($component['link'] ?? ''), 500, '') : '',
                        'button_color' => $type === 'button' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['button_color'] ?? '')) ? $component['button_color'] : '#6d4aff',
                        'button_size' => $type === 'button' && in_array($component['button_size'] ?? null, ['very-small', 'small', 'medium', 'big', 'very-big'], true) ? $component['button_size'] : 'medium',
                        'images' => $type === 'gallery' ? collect(is_array($component['images'] ?? null) ? $component['images'] : [])->map(fn ($image): array => ['id' => (string) ($image['id'] ?? Str::uuid()), 'url' => Str::limit((string) ($image['url'] ?? ''), 500, ''), 'alt' => Str::limit((string) ($image['alt'] ?? ''), 180, ''), 'position' => in_array($image['position'] ?? null, ['center', 'top', 'bottom', 'left', 'right'], true) ? $image['position'] : 'center'])->values()->all() : [],
                        'style' => $type === 'gallery' && in_array($component['style'] ?? null, ['grid', 'slider', 'masonry', 'featured', 'art-wall'], true) ? $component['style'] : 'grid',
                        'columns' => $type === 'gallery' ? max(2, min(6, (int) ($component['columns'] ?? 3))) : 0,
                        'divider_style' => $type === 'divider' && in_array($component['divider_style'] ?? null, ['solid', 'dashed', 'dotted'], true) ? $component['divider_style'] : 'solid',
                        'divider_color' => $type === 'divider' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['divider_color'] ?? '')) ? $component['divider_color'] : '#e2e8f0',
                        'divider_width' => $type === 'divider' ? max(10, min(100, (int) ($component['divider_width'] ?? 100))) : 0,
                        'divider_thickness' => $type === 'divider' ? max(1, min(8, (int) ($component['divider_thickness'] ?? 1))) : 0,
                        'divider_spacing' => $type === 'divider' ? max(0, min(120, (int) ($component['divider_spacing'] ?? 24))) : 0,
                        'event_limit' => $type === 'events' && (($component['event_limit'] ?? 3) === 'all' || in_array((int) ($component['event_limit'] ?? 0), [3, 4, 6, 8], true)) ? (($component['event_limit'] ?? 3) === 'all' ? 'all' : (int) $component['event_limit']) : 3,
                        'event_style' => $type === 'events' && in_array($component['event_style'] ?? null, ['list', 'gallery'], true) ? $component['event_style'] : 'list',
                        'event_button_color' => $type === 'events' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['event_button_color'] ?? '')) ? $component['event_button_color'] : '#6d4aff',
                        'event_button_text_color' => $type === 'events' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['event_button_text_color'] ?? '')) ? $component['event_button_text_color'] : '#ffffff',
                        'sermon_limit' => $type === 'sermons' && (($component['sermon_limit'] ?? 'all') === 'all' || in_array((int) ($component['sermon_limit'] ?? 0), [3, 4, 5, 6, 8, 9], true)) ? (($component['sermon_limit'] ?? 'all') === 'all' ? 'all' : (int) $component['sermon_limit']) : 'all',
                        'animation' => in_array($component['animation'] ?? null, ['none', 'fade', 'slide-up', 'slide-left', 'zoom', 'bounce', 'float'], true) ? $component['animation'] : 'none',
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'id' => (string) ($node['id'] ?? Str::uuid()),
            'type' => 'columns',
            'columns' => $columns === [] ? [['id' => (string) Str::uuid(), 'width' => 1, 'background_color' => 'transparent', 'background_transparent' => true, 'background_image' => '', 'background_video' => '', 'height' => 'auto', 'column_width' => 'default', 'components' => []]] : $columns,
        ];
    }

    private function removeColumnBackgrounds(array &$node, array $removeImages, array $removeVideos): void
    {
        if (($node['type'] ?? null) !== 'columns') {
            return;
        }

        if (isset($node['groups'])) {
            foreach ($node['groups'] as &$group) {
                $this->removeColumnBackgrounds($group, $removeImages, $removeVideos);
            }
            unset($group);

            return;
        }

        foreach ($node['columns'] ?? [] as &$column) {
            $columnId = (string) ($column['id'] ?? '');
            if (in_array($columnId, $removeImages, true)) {
                $column['background_image'] = '';
            }
            if (in_array($columnId, $removeVideos, true)) {
                $column['background_video'] = '';
            }
            foreach ($column['components'] ?? [] as &$component) {
                $this->removeColumnBackgrounds($component, $removeImages, $removeVideos);
            }
            unset($component);
        }
        unset($column);
    }

    private function storeComponentFiles(array &$node, array $legacyFiles, array $imageFiles, array $videoFiles, Church $church): void
    {
        if (($node['type'] ?? null) === 'columns') {
            if (isset($node['groups'])) {
                foreach ($node['groups'] as &$group) {
                    $this->storeComponentFiles($group, $legacyFiles, $imageFiles, $videoFiles, $church);
                }
                unset($group);

                return;
            }
            foreach ($node['columns'] as &$column) {
                $columnId = (string) ($column['id'] ?? '');
                $columnImageFile = $imageFiles[$columnId] ?? null;
                if ($columnImageFile instanceof UploadedFile) {
                    $column['background_image'] = $this->storeWebsiteAsset($columnImageFile, $church);
                }
                $columnVideoFile = $videoFiles[$columnId] ?? null;
                if ($columnVideoFile instanceof UploadedFile) {
                    $column['background_video'] = $this->storeWebsiteAsset($columnVideoFile, $church);
                }
                foreach ($column['components'] as &$component) {
                    $this->storeComponentFiles($component, $legacyFiles, $imageFiles, $videoFiles, $church);
                }
            }

            return;
        }

        if (($node['type'] ?? null) === 'video-slider') {
            foreach ($node['slides'] as &$slide) {
                $slideId = (string) ($slide['id'] ?? '');
                $videoFile = $videoFiles[$slideId] ?? $legacyFiles[$slideId] ?? $legacyFiles[$slideId.'-video'] ?? null;
                if ($videoFile instanceof UploadedFile) {
                    $slide['video'] = $this->storeWebsiteAsset($videoFile, $church);
                }
                $imageFile = $imageFiles[$slideId] ?? null;
                if ($imageFile instanceof UploadedFile) {
                    $slide['image'] = $this->storeWebsiteAsset($imageFile, $church);
                }
            }
            unset($slide);

            return;
        }

        if (($node['type'] ?? null) === 'carousel') {
            foreach ($node['slides'] as &$slide) {
                $slideId = (string) ($slide['id'] ?? '');
                $imageFile = $imageFiles[$slideId] ?? $legacyFiles[$slideId] ?? null;
                if ($imageFile instanceof UploadedFile) {
                    $slide['image'] = $this->storeWebsiteAsset($imageFile, $church);
                }
                $videoFile = $videoFiles[$slideId] ?? $legacyFiles[$slideId.'-video'] ?? null;
                if ($videoFile instanceof UploadedFile) {
                    $slide['video'] = $this->storeWebsiteAsset($videoFile, $church);
                }
            }
            unset($slide);

            return;
        }

        if (($node['type'] ?? null) === 'gallery') {
            foreach ($node['images'] as &$image) {
                $file = $legacyFiles[$image['id'] ?? ''] ?? null;
                if ($file instanceof UploadedFile) {
                    $image['url'] = $this->storeWebsiteAsset($file, $church);
                }
            }
            unset($image);

            return;
        }

        if (($node['type'] ?? null) === 'card') {
            $nodeId = (string) ($node['id'] ?? '');
            $videoFile = $videoFiles[$nodeId] ?? null;
            if (! $videoFile instanceof UploadedFile) {
                $videoFile = $legacyFiles[$nodeId.'-video'] ?? null;
            }
            if ($videoFile instanceof UploadedFile) {
                $node['background_video'] = $this->storeWebsiteAsset($videoFile, $church);
            }

            $file = $imageFiles[$nodeId] ?? null;
            if (! $file instanceof UploadedFile) {
                $file = $legacyFiles[$nodeId] ?? null;
            }
            if ($file instanceof UploadedFile) {
                if (str_starts_with((string) $file->getMimeType(), 'video/')) {
                    $node['background_video'] = $this->storeWebsiteAsset($file, $church);
                } else {
                    $node['url'] = $this->storeWebsiteAsset($file, $church);
                }
            }

            return;
        }

        $nodeId = (string) ($node['id'] ?? '');
        $file = ($node['type'] ?? null) === 'video'
            ? ($videoFiles[$nodeId] ?? null)
            : ($imageFiles[$nodeId] ?? null);
        if (! $file instanceof UploadedFile) {
            $file = $legacyFiles[$nodeId] ?? null;
        }
        if ($file instanceof UploadedFile) {
            $node['url'] = $this->storeWebsiteAsset($file, $church);
        }
    }

    /** @return list<array<string, mixed>> */
    private function normalizeCarouselSlides(mixed $slides): array
    {
        return collect(is_array($slides) ? $slides : [])->map(function ($slide): array {
            $slide = is_array($slide) ? $slide : [];

            return [
                'id' => (string) ($slide['id'] ?? Str::uuid()),
                'image' => Str::limit((string) ($slide['image'] ?? ''), 500, ''),
                'video' => Str::limit((string) ($slide['video'] ?? ''), 500, ''),
                'title' => Str::limit((string) ($slide['title'] ?? ''), 180, ''),
                'text' => Str::limit((string) ($slide['text'] ?? ''), 500, ''),
                'link' => Str::limit((string) ($slide['link'] ?? ''), 500, ''),
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function normalizeVideoSlides(mixed $slides): array
    {
        return collect(is_array($slides) ? $slides : [])->map(function ($slide): array {
            $slide = is_array($slide) ? $slide : [];

            return [
                'id' => (string) ($slide['id'] ?? Str::uuid()),
                'video' => Str::limit((string) ($slide['video'] ?? ''), 500, ''),
                'image' => Str::limit((string) ($slide['image'] ?? ''), 500, ''),
                'title' => Str::limit((string) ($slide['title'] ?? ''), 180, ''),
                'text' => Str::limit((string) ($slide['text'] ?? ''), 500, ''),
                'link' => Str::limit((string) ($slide['link'] ?? ''), 500, ''),
            ];
        })->values()->all();
    }

    private function saveWebsiteSettings(Church $church, array $settings): void
    {
        $church->forceFill(['settings' => array_merge($church->settings ?? [], ['website' => $settings])])->save();
    }

    private function syncPageCustomSections(Request $request, WebsitePage $page): void
    {
        $church = $page->church;
        $settings = $this->websiteSettings($church);
        $selected = $request->input('custom_section_ids', []);
        $settings['custom_sections'] = collect($settings['custom_sections'] ?? [])->map(function (array $section) use ($page, $selected): array {
            $pageSlugs = collect($section['page_slugs'] ?? [])->reject(fn (string $slug): bool => $slug === $page->slug)->values();
            if (in_array((string) ($section['id'] ?? ''), $selected, true)) {
                $pageSlugs->push($page->slug);
            }
            $section['page_slugs'] = $pageSlugs->unique()->values()->all();

            return $section;
        })->values()->all();
        $this->saveWebsiteSettings($church, $settings);
    }

    private function ensureHomepage(Church $church, array $settings): WebsitePage
    {
        return $church->websitePages()->firstOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'Home',
                'status' => 'published',
                'sections' => ['hero', 'welcome', 'services', 'events', 'ministries', 'locations', 'sermons', 'store', 'giving', 'contact'],
                'published_at' => now(),
            ],
        );
    }

    private function ensureStarterPages(Church $church, array $settings): void
    {
        foreach (app(WebsiteStarterContent::class)->pages((string) ($settings['template'] ?? 'main'), (string) ($settings['site_name'] ?? $church->name)) as $definition) {
            $page = $church->websitePages()->where('slug', $definition['slug'])->first();
            if ($page === null) {
                $church->websitePages()->create($definition);

                continue;
            }

            if ((bool) data_get($page->design, 'starter') && data_get($page->design, 'starter_template') !== $settings['template']) {
                $page->update([
                    'title' => $definition['title'],
                    'body' => $definition['body'],
                    'sections' => $definition['sections'],
                    'design' => $definition['design'],
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeNavigationItems(array $items, bool $visibleOnly = false): array
    {
        return collect($items)->filter(fn ($item): bool => is_array($item))->map(function (array $item) use ($visibleOnly): array {
            $type = in_array($item['type'] ?? 'link', ['link', 'dropdown', 'mega'], true)
                ? (string) ($item['type'] ?? 'link')
                : 'link';
            $children = collect($item['children'] ?? [])->filter(fn ($child): bool => is_array($child))
                ->map(fn (array $child): array => [
                    'label' => Str::limit(trim((string) ($child['label'] ?? '')), 60, ''),
                    'url' => trim((string) ($child['url'] ?? '')),
                    'visible' => (bool) ($child['visible'] ?? false),
                    'description' => Str::limit(trim((string) ($child['description'] ?? '')), 120, ''),
                    'column' => max(1, min(4, (int) ($child['column'] ?? 1))),
                ])
                ->filter(fn (array $child): bool => $child['label'] !== '' && $child['url'] !== '')
                ->when($visibleOnly, fn ($children) => $children->where('visible', true))
                ->values()
                ->all();

            if ($type !== 'link' && $visibleOnly && $children === []) {
                $type = 'link';
            }

            return [
                'label' => Str::limit(trim((string) ($item['label'] ?? '')), 60, ''),
                'url' => trim((string) ($item['url'] ?? '')),
                'visible' => (bool) ($item['visible'] ?? false),
                'type' => $type,
                'children' => $type === 'link' ? [] : $children,
            ];
        })->filter(fn (array $item): bool => $item['label'] !== '' && $item['url'] !== '')
            ->when($visibleOnly, fn ($items) => $items->where('visible', true))
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function websiteNavigation(Church $church): array
    {
        $configured = data_get($this->websiteSettings($church), 'navigation');
        if (is_array($configured)) {
            return $this->normalizeNavigationItems($configured, true);
        }

        $slugs = ['ministries', 'about', 'our-sermons', 'our-locations', 'events', 'contact', 'store'];
        $pages = $church->websitePages()->whereIn('slug', $slugs)->where('status', 'published')->get()->keyBy('slug');

        return collect($slugs)->map(function (string $slug) use ($church, $pages): ?array {
            $page = $pages->get($slug);
            if ($page === null) {
                return null;
            }

            return [
                'label' => $page->title,
                'url' => route('website.public', $slug === 'home'
                    ? ['church' => $church->slug]
                    : ['church' => $church->slug, 'page' => $slug]),
                'visible' => true,
                'type' => 'link',
                'children' => [],
            ];
        })->filter()->values()->all();
    }

    /** @return array<string, string> */
    private function templates(): array
    {
        return [
            'main' => 'Grace & Community',
        ];
    }

    /** @return array<string, array{label: string, description: string}> */
    private function menuStyles(): array
    {
        return [
            'classic' => [
                'label' => 'Classic',
                'description' => 'A balanced full-width header with clear navigation and actions.',
            ],
            'floating' => [
                'label' => 'Floating glass',
                'description' => 'A premium rounded header that floats above the page.',
            ],
            'centered' => [
                'label' => 'Centered',
                'description' => 'A refined layout with the menu centered between the brand and actions.',
            ],
            'pill' => [
                'label' => 'Navigation pill',
                'description' => 'Menu links sit inside a modern, softly elevated capsule.',
            ],
            'accent' => [
                'label' => 'Bold brand',
                'description' => 'A confident color-forward topbar using your website palette.',
            ],
        ];
    }

    /** @return array<string, string> */
    private function sectionTypes(): array
    {
        return [
            'hero' => 'Welcome hero',
            'welcome' => 'Welcome message',
            'experience' => 'Experience cards',
            'services' => 'Service times',
            'events' => 'Upcoming events',
            'ministries' => 'Ministry cards',
            'locations' => 'Our locations',
            'sermons' => 'Sermon library',
            'store' => 'Bookstore products',
            'giving' => 'Giving call-to-action',
            'contact' => 'Visit and contact',
        ];
    }

    /** @return array<string, mixed> */
    private function validatedPage(Request $request, Church $church): array
    {
        $customSectionIds = collect($this->websiteSettings($church)['custom_sections'] ?? [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
        $sectionOrderOptions = array_values(array_unique(array_merge(array_keys($this->sectionTypes()), $customSectionIds)));

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'alpha_dash', 'max:100'],
            'status' => ['required', 'in:draft,published'],
            'body' => ['nullable', 'string', 'max:30000'],
            'section_types' => ['nullable', 'array'],
            'section_types.*' => ['string', Rule::in(array_keys($this->sectionTypes()))],
            'section_order' => ['nullable', 'array'],
            'section_order.*' => ['string', Rule::in($sectionOrderOptions)],
            'custom_section_ids' => ['nullable', 'array'],
            'custom_section_ids.*' => ['string', 'max:80', Rule::in($customSectionIds)],
            'section_selection_configured' => ['nullable', 'boolean'],
            'page_template' => ['nullable', Rule::in(array_merge(['inherit'], array_keys($this->templates())))],
            'page_primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'page_accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'page_hero_eyebrow' => ['nullable', 'string', 'max:100'],
            'page_hero_heading' => ['nullable', 'string', 'max:180'],
            'page_hero_body' => ['nullable', 'string', 'max:1000'],
            'page_hero_image_url' => ['nullable', 'string', 'max:500'],
            'page_hero_image_file' => ['nullable', 'image', 'max:15360'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:180'],
        ]);
        $slug = Str::slug($validated['slug'] ?: $validated['title']);

        if ($request->route('page') instanceof WebsitePage && $request->route('page')->slug === 'home') {
            $slug = 'home';
        }

        $validated['slug'] = $slug;
        $selectedSections = $request->boolean('section_selection_configured')
            ? array_values($validated['section_types'] ?? [])
            : array_values($validated['section_types'] ?? ['welcome', 'contact']);
        $selectedSections = array_values(array_unique(array_merge($selectedSections, $validated['custom_section_ids'] ?? [])));
        $orderedSections = array_values($validated['section_order'] ?? []);
        $validated['sections'] = array_values(array_unique(array_merge(
            array_values(array_filter($orderedSections, fn (string $section): bool => in_array($section, $selectedSections, true))),
            array_values(array_diff($selectedSections, $orderedSections)),
        )));
        unset($validated['section_types']);
        unset($validated['section_order']);
        unset($validated['custom_section_ids']);
        unset($validated['section_selection_configured']);

        $page = $request->route('page') instanceof WebsitePage ? $request->route('page') : null;
        $design = $page?->design ?? [];
        if (($design['template'] ?? null) !== null && ! array_key_exists((string) $design['template'], $this->templates())) {
            unset($design['template']);
        }
        $designFields = [
            'page_template' => 'template',
            'page_primary_color' => 'primary_color',
            'page_accent_color' => 'accent_color',
            'page_hero_eyebrow' => 'hero_eyebrow',
            'page_hero_heading' => 'hero_heading',
            'page_hero_body' => 'hero_body',
            'page_hero_image_url' => 'hero_image_url',
        ];
        foreach ($designFields as $requestKey => $designKey) {
            if (! array_key_exists($requestKey, $validated)) {
                continue;
            }

            $value = $validated[$requestKey];
            unset($validated[$requestKey]);

            if ($requestKey === 'page_template' && $value === 'inherit') {
                unset($design[$designKey]);
            } elseif (filled($value)) {
                $design[$designKey] = $value;
            } else {
                unset($design[$designKey]);
            }
        }
        if ($page !== null) {
            unset($design['starter'], $design['starter_template']);
        }
        $validated['design'] = $design === [] ? null : $design;

        $query = $church->websitePages()->where('slug', $slug);
        if ($request->route('page') instanceof WebsitePage) {
            $query->whereKeyNot($request->route('page')->getKey());
        }
        abort_if($query->exists(), 422, 'A page with this URL already exists.');

        return $validated;
    }

    private function storeWebsiteAsset(UploadedFile $file, Church $church): string
    {
        $path = $file->store('website/'.$church->id, 'public');
        $settings = $this->websiteSettings($church);
        $settings['media_library'][] = [
            'id' => (string) Str::uuid(),
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'type' => $file->getMimeType(),
            'uploaded_at' => now()->toIso8601String(),
        ];
        $this->saveWebsiteSettings($church, $settings);

        return $path;
    }
}
