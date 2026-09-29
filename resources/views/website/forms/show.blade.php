<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $form['title'] }} — {{ $church->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/website/forms.css') }}?v={{ filemtime(public_path('css/website/forms.css')) }}">
</head>
<body class="church-form-page" style="--form-accent: {{ $form['accent_color'] }}; --form-button-text: {{ \App\Support\WebsiteForms::buttonTextColor($form['accent_color']) }}; --form-background: {{ $form['background_color'] }}; --form-radius: {{ $form['style'] === 'square' ? '0' : '16px' }}; --form-gap: {{ $form['spacing'] === 'compact' ? '12px' : '24px' }}">
    <main class="church-form-card">
        <a class="church-form-back" href="{{ route('website.public', ['church' => $church->slug]) }}">← {{ $church->name }}</a>
        <header class="church-form-heading">
            <span class="church-form-kicker">{{ ['prayer' => 'We are here for you', 'testimony' => 'Your story matters', 'feedback' => 'We are listening', 'contact' => 'Let’s connect'][$type] ?? 'Let’s connect' }}</span>
            <h1>{{ $form['title'] }}</h1>
            @if($form['description'])<p>{{ $form['description'] }}</p>@endif
        </header>
        @if(session('form_success'))
            <div class="church-form-success" role="status">{{ session('form_success') }}</div>
        @else
            @if($errors->any())
                <div class="church-form-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('website.forms.store', ['church' => $church->slug, 'type' => $type]) }}" class="church-form-fields">
                @csrf
                @include('website.forms.fields')
            </form>
        @endif
    </main>
</body>
</html>
