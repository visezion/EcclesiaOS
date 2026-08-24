<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

final class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(['en', 'fr', 'es'])],
        ]);

        $locale = (string) $validated['locale'];
        $request->session()->put('locale', $locale);
        app()->setLocale($locale);
        Cookie::queue(cookie('locale', $locale, 60 * 24 * 365, '/', null, $request->isSecure(), false, false, 'lax'));

        if ($user = $request->user()) {
            $settings = $user->account_settings ?? [];
            data_set($settings, 'preferences.language', $locale);
            $user->forceFill(['account_settings' => $settings])->save();
        }

        return back();
    }
}
