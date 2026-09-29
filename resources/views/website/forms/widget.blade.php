@php($widgetForm = \App\Support\WebsiteForms::forWidget($church, $component))
@php($widgetHasOldInput = old('widget_id') === ($component['id'] ?? null) && old('section_id') === ($customSection['id'] ?? null))
@if($widgetForm && $widgetForm['enabled'] && data_get($church->settings, 'website.enabled', true))
    @once
        <link rel="stylesheet" href="{{ asset('css/website/forms.css') }}?v={{ filemtime(public_path('css/website/forms.css')) }}">
        <script src="{{ asset('js/website/forms.js') }}?v={{ filemtime(public_path('js/website/forms.js')) }}" defer></script>
    @endonce
    <div class="church-form-page church-form-widget" data-church-form-widget style="--form-accent: {{ $widgetForm['accent_color'] }}; --form-button-text: {{ \App\Support\WebsiteForms::buttonTextColor($widgetForm['accent_color']) }}; --form-background: {{ $widgetForm['background_color'] }}; --form-radius: {{ $widgetForm['style'] === 'square' ? '0' : '16px' }}; --form-gap: {{ $widgetForm['spacing'] === 'compact' ? '12px' : '24px' }}">
        <div class="church-form-card">
            <header class="church-form-heading">
                <span class="church-form-kicker">{{ ['prayer' => 'We are here for you', 'testimony' => 'Your story matters', 'feedback' => 'We are listening', 'contact' => 'Let’s connect'][$formType] ?? 'Let’s connect' }}</span>
                <h2>{{ $widgetForm['title'] }}</h2>
                @if($widgetForm['description'])<p>{{ $widgetForm['description'] }}</p>@endif
            </header>
            @if(session('widget_success.id') === ($component['id'] ?? null) && session('widget_success.section') === ($customSection['id'] ?? null))
                <div class="church-form-success" role="status">{{ session('widget_success.message') }}</div>
            @endif
            <div data-form-result tabindex="-1" aria-live="polite" hidden></div>
            @if($widgetHasOldInput && $errors->any())
                <div class="church-form-errors" role="alert">{{ $errors->first() }}</div>
            @endif
            @if($preview ?? false)<p>Preview only. Visitors can submit this form on the published page.</p>@endif
            <form method="POST" action="{{ route('website.forms.store', ['church' => $church->slug, 'type' => $formType]) }}" data-inline-church-form>
                @csrf
                <input type="hidden" name="widget_id" value="{{ $component['id'] }}">
                <input type="hidden" name="section_id" value="{{ $customSection['id'] }}">
                <fieldset class="church-form-fields" @disabled($preview ?? false)>
                    @include('website.forms.fields', ['form' => $widgetForm, 'useOldInput' => $widgetHasOldInput])
                </fieldset>
            </form>
        </div>
    </div>
@endif
