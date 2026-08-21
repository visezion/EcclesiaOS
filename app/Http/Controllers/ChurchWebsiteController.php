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
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Support\ModuleRegistry;
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
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Website Studio', 'url' => null],
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeStudio($request);
        $church = $this->studioChurch($request);
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'template' => ['required', 'in:main'],
            'site_name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'hero_image_url' => ['nullable', 'string', 'max:500'],
            'hero_video_url' => ['nullable', 'string', 'max:500'],
            'logo_file' => ['nullable', 'image', 'max:10240'],
            'hero_image_file' => ['nullable', 'image', 'max:15360'],
            'hero_video_file' => ['nullable', 'mimetypes:video/mp4,video/webm,video/ogg', 'max:51200'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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

    private function renderWebsite(Church $church, WebsitePage $page, bool $preview = false): View
    {
        $settings = array_merge(
            $this->websiteSettings($church),
            collect($page->design ?? [])->filter(fn ($value): bool => filled($value))->all(),
        );
        $settings['template'] = 'main';

        $template = $settings['template'] ?? 'main';
        $templateView = view()->exists('website.templates.'.$template.'.index')
            ? 'website.templates.'.$template.'.index'
            : 'website.public';
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
                ? Event::query()->where('church_id', $church->id)->where('show_on_website', true)->whereIn('status', ['scheduled', 'published'])->where('starts_at', '>', now())->orderBy('starts_at')->limit(6)->get()
                : collect(),
            'ministries' => Ministry::query()->where('church_id', $church->id)->where('status', 'active')->orderBy('name')->limit(6)->get(),
            'campuses' => Campus::query()->where('church_id', $church->id)->where('status', 'active')->orderBy('name')->limit(8)->get(),
            'sermons' => Sermon::query()->where('church_id', $church->id)->where('status', 'published')->latest('preached_at')->latest('id')->limit(6)->get(),
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
        $settings = array_merge([
            'enabled' => true,
            'template' => 'main',
            'site_name' => $church->name,
            'tagline' => 'A place to belong, become, and believe.',
            'logo_url' => null,
            'hero_image_url' => null,
            'hero_video_url' => null,
            'primary_color' => '#4338CA',
            'accent_color' => '#F59E0B',
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

        if (($settings['template'] ?? null) !== 'main') {
            $settings['template'] = 'main';
        }

        return $settings;
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
            'page_slugs' => ['nullable', 'array'],
            'page_slugs.*' => ['string', 'alpha_dash', 'max:100'],
            'components' => ['nullable', 'string', 'max:50000'],
            'column_widths' => ['nullable', 'array'],
            'column_widths.*' => ['integer', 'min:5', 'max:95'],
            'component_files' => ['nullable', 'array'],
            'component_files.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,ogg', 'max:51200'],
        ]);
        $data['page_slugs'] = array_values($data['page_slugs'] ?? ['home']);
        $data['components'] = $this->normalizeSectionComponents($data['components'] ?? null);
        $columnCount = max(1, min(4, ((int) collect($data['components'])->max('column')) + 1));
        $widths = array_values(array_map('intval', $data['column_widths'] ?? []));
        $data['column_widths'] = count($widths) === $columnCount
            ? array_map(fn (int $width): int => max(5, min(95, $width)), $widths)
            : array_fill(0, $columnCount, 1);
        $this->storeComponentFiles($data['components'], $request->file('component_files', []), $church);
        if ($request->hasFile('image_file')) {
            $data['image_url'] = $this->storeWebsiteAsset($request->file('image_file'), $church);
        }
        if ($request->hasFile('video_file')) {
            $data['video_url'] = $this->storeWebsiteAsset($request->file('video_file'), $church);
        }
        unset($data['image_file'], $data['video_file'], $data['component_files']);

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
            $type = in_array($component['type'] ?? null, ['heading', 'text', 'quote', 'image', 'video', 'button', 'spacer', 'carousel', 'video-slider', 'gallery', 'card', 'icon', 'divider', 'events'], true)
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
                'height' => $type === 'spacer' ? max(0, min(600, (int) ($component['height'] ?? 36))) : 0,
                'title' => in_array($type, ['card', 'icon', 'gallery'], true) ? Str::limit((string) ($component['title'] ?? ''), 180, '') : '',
                'body' => in_array($type, ['card', 'icon'], true) ? Str::limit((string) ($component['body'] ?? ''), 1000, '') : '',
                'background_color' => in_array($type, ['card', 'icon'], true) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['background_color'] ?? '')) ? $component['background_color'] : ($type === 'icon' ? '#ede9fe' : '#6d4aff'),
                'background_video' => $type === 'card' ? Str::limit((string) ($component['background_video'] ?? ''), 500, '') : '',
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
                'event_limit' => $type === 'events' && in_array((int) ($component['event_limit'] ?? 3), [3, 6], true) ? (int) $component['event_limit'] : 3,
                'event_button_color' => $type === 'events' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['event_button_color'] ?? '')) ? $component['event_button_color'] : '#6d4aff',
                'event_button_text_color' => $type === 'events' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['event_button_text_color'] ?? '')) ? $component['event_button_text_color'] : '#ffffff',
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
                'width' => max(1, min(95, (int) ($column['width'] ?? 1))),
                'components' => collect($column['components'] ?? [])->filter(fn ($component): bool => is_array($component))->map(function (array $component): array {
                    if (($component['type'] ?? null) === 'columns') {
                        return $this->normalizeColumnNode($component);
                    }

                    $type = in_array($component['type'] ?? null, ['heading', 'text', 'quote', 'image', 'video', 'button', 'spacer', 'carousel', 'video-slider', 'gallery', 'card', 'icon', 'divider', 'events'], true)
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
                        'height' => $type === 'spacer' ? max(0, min(600, (int) ($component['height'] ?? 36))) : 0,
                        'title' => in_array($type, ['card', 'icon', 'gallery'], true) ? Str::limit((string) ($component['title'] ?? ''), 180, '') : '',
                        'body' => in_array($type, ['card', 'icon'], true) ? Str::limit((string) ($component['body'] ?? ''), 1000, '') : '',
                        'background_color' => in_array($type, ['card', 'icon'], true) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['background_color'] ?? '')) ? $component['background_color'] : ($type === 'icon' ? '#ede9fe' : '#6d4aff'),
                        'background_video' => $type === 'card' ? Str::limit((string) ($component['background_video'] ?? ''), 500, '') : '',
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
                        'event_limit' => $type === 'events' && in_array((int) ($component['event_limit'] ?? 3), [3, 6], true) ? (int) $component['event_limit'] : 3,
                        'event_button_color' => $type === 'events' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['event_button_color'] ?? '')) ? $component['event_button_color'] : '#6d4aff',
                        'event_button_text_color' => $type === 'events' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($component['event_button_text_color'] ?? '')) ? $component['event_button_text_color'] : '#ffffff',
                        'animation' => in_array($component['animation'] ?? null, ['none', 'fade', 'slide-up', 'slide-left', 'zoom', 'bounce', 'float'], true) ? $component['animation'] : 'none',
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'id' => (string) ($node['id'] ?? Str::uuid()),
            'type' => 'columns',
            'columns' => $columns === [] ? [['width' => 1, 'components' => []]] : $columns,
        ];
    }

    private function storeComponentFiles(array &$node, array $files, Church $church): void
    {
        if (($node['type'] ?? null) === 'columns') {
            if (isset($node['groups'])) {
                foreach ($node['groups'] as &$group) {
                    $this->storeComponentFiles($group, $files, $church);
                }
                unset($group);
                return;
            }
            foreach ($node['columns'] ?? [] as &$column) {
                foreach ($column['components'] ?? [] as &$component) {
                    $this->storeComponentFiles($component, $files, $church);
                }
            }

            return;
        }

        if (in_array($node['type'] ?? null, ['carousel', 'video-slider'], true)) {
            foreach ($node['slides'] ?? [] as &$slide) {
                $file = $files[$slide['id'] ?? ''] ?? null;
                if ($file instanceof UploadedFile) {
                    $isVideo = $node['type'] === 'video-slider' || str_starts_with((string) $file->getMimeType(), 'video/');
                    $slide[$isVideo ? 'video' : 'image'] = $this->storeWebsiteAsset($file, $church);
                }
                $videoFile = $files[($slide['id'] ?? '').'-video'] ?? null;
                if ($node['type'] === 'carousel' && $videoFile instanceof UploadedFile) {
                    $slide['video'] = $this->storeWebsiteAsset($videoFile, $church);
                }
            }
            unset($slide);
            return;
        }

        if (($node['type'] ?? null) === 'gallery') {
            foreach ($node['images'] ?? [] as &$image) {
                $file = $files[$image['id'] ?? ''] ?? null;
                if ($file instanceof UploadedFile) {
                    $image['url'] = $this->storeWebsiteAsset($file, $church);
                }
            }
            unset($image);

            return;
        }

        if (($node['type'] ?? null) === 'card') {
            $videoFile = $files[($node['id'] ?? '').'-video'] ?? null;
            if ($videoFile instanceof UploadedFile) {
                $node['background_video'] = $this->storeWebsiteAsset($videoFile, $church);
            }

            $file = $files[$node['id'] ?? ''] ?? null;
            if ($file instanceof UploadedFile) {
                if (str_starts_with((string) $file->getMimeType(), 'video/')) {
                    $node['background_video'] = $this->storeWebsiteAsset($file, $church);
                } else {
                    $node['url'] = $this->storeWebsiteAsset($file, $church);
                }
            }

            return;
        }

        $file = $files[$node['id'] ?? ''] ?? null;
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

    /** @return list<array{label: string, url: string}> */
    private function websiteNavigation(Church $church): array
    {
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

    /** @return array<string, string> */
    private function sectionTypes(): array
    {
        return [
            'hero' => 'Welcome hero',
            'welcome' => 'Welcome message',
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
            'section_types.*' => ['string', 'in:hero,welcome,services,events,ministries,locations,sermons,store,giving,contact'],
            'section_order' => ['nullable', 'array'],
            'section_order.*' => ['string', Rule::in($sectionOrderOptions)],
            'custom_section_ids' => ['nullable', 'array'],
            'custom_section_ids.*' => ['string', 'max:80', Rule::in($customSectionIds)],
            'page_template' => ['nullable', 'in:inherit,main'],
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
        $selectedSections = array_values($validated['section_types'] ?? ['welcome', 'contact']);
        $selectedSections = array_values(array_unique(array_merge($selectedSections, $validated['custom_section_ids'] ?? [])));
        $orderedSections = array_values($validated['section_order'] ?? []);
        $validated['sections'] = array_values(array_unique(array_merge(
            array_values(array_filter($orderedSections, fn (string $section): bool => in_array($section, $selectedSections, true))),
            array_values(array_diff($selectedSections, $orderedSections)),
        )));
        unset($validated['section_types']);
        unset($validated['section_order']);
        unset($validated['custom_section_ids']);

        $page = $request->route('page') instanceof WebsitePage ? $request->route('page') : null;
        $design = $page?->design ?? [];
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
