<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php($branding = \App\Support\Branding::current())
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('code') - {{ $branding->systemName() }}</title>
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; background: #f6f8fc; color: #0f172a; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
            main { display: grid; min-height: 100vh; place-items: center; padding: 2.5rem 1rem; }
            section { width: 100%; max-width: 34rem; overflow: hidden; border: 1px solid #dfe6f2; border-radius: 12px; background: #fff; padding: 2rem; text-align: center; box-shadow: 0 18px 44px rgb(15 23 42 / 0.10); }
            .mark { display: grid; width: 4rem; height: 4rem; margin: 0 auto; place-items: center; border-radius: 1rem; background: #ede9fe; color: #6d4aff; font-size: 2rem; font-weight: 700; }
            .code { margin: 1.25rem 0 0; color: #6d4aff; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            h1 { margin: 0.5rem 0 0; color: #020617; font-size: 1.55rem; line-height: 1.18; }
            .message { margin: 0.75rem 0 0; color: #64748b; font-size: 0.95rem; line-height: 1.6; }
            a { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; margin-top: 1.5rem; border-radius: 0.5rem; background: #6d4aff; padding: 0.72rem 1rem; color: #fff; font-size: 0.875rem; font-weight: 700; text-decoration: none; }
            a:hover { background: #5b38ef; }
        </style>
    </head>
    <body>
        <main>
            <section>
                <div class="mark">!</div>
                <p class="code">@yield('code')</p>
                <h1>@yield('title')</h1>
                <p class="message">@yield('message')</p>
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}">Return safely</a>
            </section>
        </main>
    </body>
</html>
