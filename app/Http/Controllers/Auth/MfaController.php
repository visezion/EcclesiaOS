<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TotpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class MfaController extends Controller
{
    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.mfa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.mfa-challenge');
    }

    public function verify(Request $request, TotpService $totpService, ActivityLogger $activityLogger): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = User::query()->find($request->session()->get('login.mfa_user_id'));

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Your sign-in session expired. Please sign in again.']);
        }

        $settings = $user->account_settings ?? [];
        $secret = Crypt::decryptString((string) data_get($settings, 'security.mfa_secret_encrypted'));
        $code = trim((string) $request->input('code'));
        $validTotp = $totpService->verify($secret, $code);
        $validRecovery = false;

        if (! $validTotp) {
            [$validRecovery, $settings] = $this->consumeRecoveryCode($settings, $code);
        }

        if (! $validTotp && ! $validRecovery) {
            $activityLogger->log('Authentication', 'failed_login', 'Invalid MFA code.', $user, ['resource' => 'MFA', 'status' => 'failed'], $request);

            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid or expired.',
            ]);
        }

        if ($validRecovery) {
            $user->forceFill(['account_settings' => $settings])->save();
        }

        Auth::login($user, (bool) $request->session()->pull('login.remember', false));
        $request->session()->forget('login.mfa_user_id');
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        $activityLogger->log('Authentication', 'login', 'User signed in with MFA.', $user, ['resource' => 'MFA', 'status' => 'success'], $request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function consumeRecoveryCode(array $settings, string $code): array
    {
        $submitted = str($code)->upper()->replace(' ', '')->toString();
        $hashes = data_get($settings, 'security.mfa_recovery_code_hashes', []);

        foreach ($hashes as $index => $hash) {
            if (Hash::check($submitted, $hash)) {
                unset($hashes[$index]);
                data_set($settings, 'security.mfa_recovery_code_hashes', array_values($hashes));

                return [true, $settings];
            }
        }

        return [false, $settings];
    }
}
