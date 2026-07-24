<x-app-layout title="MFA Setup" :breadcrumbs="$breadcrumbs">
    <div class="space-y-5">
        <section class="dashboard-card p-0">
            <div class="grid gap-6 p-5 xl:grid-cols-[minmax(0,1fr)_320px]">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-lg bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-700 ring-1 ring-violet-100">
                        <i data-lucide="shield-check" class="size-4"></i>
                        Authenticator app
                    </div>
                    <h1 class="mt-4 text-2xl font-semibold text-slate-950">Set up multi-factor authentication</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-7 text-slate-500">Scan the code with Google Authenticator, Microsoft Authenticator, 1Password, or another TOTP app. Then enter the 6-digit code to activate MFA on your account.</p>
                </div>
                <aside class="rounded-lg border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-800">
                    <i data-lucide="lock-keyhole" class="mb-3 size-6"></i>
                    MFA protects your dashboard even if your password is exposed.
                </aside>
            </div>
        </section>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>
        @endif

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <article class="dashboard-card">
                <h2 class="text-base font-semibold text-slate-950">1. Scan the setup code</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Open your authenticator app, choose add account, then scan this QR code.</p>
                <div class="mt-5 inline-block rounded-xl border border-slate-200 bg-white p-4">
                    {!! $qrSvg !!}
                </div>
                <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <div class="text-xs font-semibold uppercase text-slate-400">Manual setup key</div>
                    <div class="mt-2 break-all font-mono text-sm font-semibold text-slate-950">{{ $secret }}</div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Use this key only if your app cannot scan the QR code.</p>
                </div>
            </article>

            <aside class="space-y-5">
                <form method="POST" action="{{ route('account.mfa.confirm') }}" class="dashboard-card">
                    @csrf
                    <h2 class="text-base font-semibold text-slate-950">2. Confirm it works</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Enter the current 6-digit code from your authenticator app.</p>
                    <label class="mt-4 block text-sm font-semibold text-slate-700">Authenticator code
                        <input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-center text-lg font-semibold tracking-wide">
                    </label>
                    <button class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
                        <i data-lucide="shield-check" class="size-4"></i>
                        Enable MFA
                    </button>
                </form>

                <form method="POST" action="{{ route('account.mfa.recovery-codes') }}" class="dashboard-card">
                    @csrf
                    <h2 class="text-base font-semibold text-slate-950">Recovery codes</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Use these one-time backup codes if your authenticator app is unavailable.</p>
                    @if($recoveryCodes)
                        <div class="mt-4 grid gap-2">
                            @foreach($recoveryCodes as $code)
                                <div class="rounded-lg bg-slate-50 px-3 py-2 font-mono text-sm font-semibold text-slate-950">{{ $code }}</div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-500">{{ $user->mfa_enabled ? 'Recovery codes are already generated. Regenerate them if you need a fresh set.' : 'Recovery codes appear after you confirm your authenticator app.' }}</div>
                    @endif
                    @if($user->mfa_enabled)
                        <button class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-violet-200 px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50">
                            <i data-lucide="refresh-cw" class="size-4"></i>
                            Regenerate codes
                        </button>
                    @endif
                </form>
            </aside>
        </section>
    </div>
</x-app-layout>
