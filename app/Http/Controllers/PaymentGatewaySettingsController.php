<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Church;
use App\Services\ActivityLogger;
use App\Services\PaymentGatewaySettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PaymentGatewaySettingsController extends Controller
{
    public function index(Request $request, PaymentGatewaySettings $settings): View
    {
        $this->authorizeAccess($request);
        $church = $this->church($request);

        return view('admin.payment-gateways', [
            'church' => $church,
            'gateways' => collect(PaymentGatewaySettings::PROVIDERS)->mapWithKeys(
                fn (string $provider): array => [$provider => $settings->forChurch($church, $provider)]
            )->all(),
            'webhookUrls' => [
                'stripe' => route('webhooks.stripe'),
                'paystack' => route('webhooks.payment', 'paystack'),
                'paypal' => route('webhooks.payment', 'paypal'),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Giving & Finance', 'url' => route('finance.index')],
                ['label' => 'Payment Gateways', 'url' => null],
            ],
        ]);
    }

    public function update(Request $request, PaymentGatewaySettings $settings, ActivityLogger $activityLogger, string $provider = 'stripe'): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless(in_array($provider, PaymentGatewaySettings::PROVIDERS, true), 404);
        $church = $this->church($request);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'mode' => ['required', 'in:test,live'],
            'publishable_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'webhook_id' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);
        $current = $settings->forChurch($church, $provider);
        $publishable = trim((string) (($validated['publishable_key'] ?? null) ?: $current['publishable_key']));
        $secret = trim((string) (($validated['secret_key'] ?? null) ?: $current['secret_key']));
        $webhook = trim((string) (($validated['webhook_secret'] ?? null) ?: $current['webhook_secret']));
        $clientId = trim((string) (($validated['client_id'] ?? null) ?: $current['client_id']));
        $clientSecret = trim((string) (($validated['client_secret'] ?? null) ?: $current['client_secret']));
        $webhookId = trim((string) (($validated['webhook_id'] ?? null) ?: $current['webhook_id']));
        $prefix = $validated['mode'] === 'test' ? 'test' : 'live';
        $errors = [];

        if ($provider === 'stripe' && (bool) $validated['enabled'] && ! str_starts_with($publishable, 'pk_'.$prefix.'_')) {
            $errors['publishable_key'] = 'Enter a valid Stripe '.$prefix.' publishable key.';
        }
        if (in_array($provider, ['stripe', 'paystack'], true) && (bool) $validated['enabled'] && ! str_starts_with($secret, 'sk_'.$prefix.'_')) {
            $errors['secret_key'] = 'Enter a valid '.str($provider)->headline().' '.$prefix.' secret key.';
        }
        if ($provider === 'stripe' && (bool) $validated['enabled'] && ! str_starts_with($webhook, 'whsec_')) {
            $errors['webhook_secret'] = 'Enter a valid Stripe webhook signing secret.';
        }
        if ($provider === 'paypal' && (bool) $validated['enabled'] && ($clientId === '' || $clientSecret === '')) {
            $errors['client_id'] = 'Enter the PayPal client ID and client secret.';
        }
        if ($provider === 'paypal' && (bool) $validated['enabled'] && $webhookId === '') {
            $errors['webhook_id'] = 'Enter the PayPal webhook ID used to verify notifications.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $settings->save($church, $validated, $provider);
        $label = str($provider)->headline()->toString();
        $activityLogger->log(
            'Settings',
            'payment_gateway_updated',
            $label.' payment gateway settings were updated.',
            $church,
            ['provider' => $provider, 'mode' => $validated['mode'], 'enabled' => (bool) $validated['enabled']],
            $request,
        );

        return back()->with('status', $label.' payment gateway settings saved securely.');
    }

    public function test(Request $request, PaymentGateway $gateway, PaymentGatewaySettings $settings, string $provider = 'stripe'): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless(in_array($provider, PaymentGatewaySettings::PROVIDERS, true), 404);
        $church = $this->church($request);

        try {
            $result = $gateway->testConnection($church, $provider);
            $settings->recordTest($church, true, $result['message'], $provider);

            return back()->with('status', $result['message']);
        } catch (Throwable $exception) {
            report($exception);
            $message = str($provider)->headline().' connection failed. Check the saved mode and credentials.';
            $settings->recordTest($church, false, $message, $provider);

            return back()->withErrors(['connection' => $message]);
        }
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || $user?->hasPermission('manage settings'), 403);
    }

    private function church(Request $request): Church
    {
        return $request->user()?->church_id
            ? Church::query()->findOrFail($request->user()->church_id)
            : Church::query()->firstOrFail();
    }
}
