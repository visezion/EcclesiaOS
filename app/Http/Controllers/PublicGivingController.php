<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Fund;
use App\Models\PaymentGatewayTransaction;
use App\Services\PaymentRecorder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

final class PublicGivingController extends Controller
{
    public function create(PaymentGateway $gateway): View
    {
        $church = Church::query()->firstOrFail();

        return view('finance.give', [
            'church' => $church,
            'funds' => Fund::query()->where('church_id', $church->id)->where('is_active', true)->orderBy('name')->get(),
            'campuses' => Campus::query()->where('church_id', $church->id)->where('status', 'active')->orderBy('name')->get(),
            'providers' => $gateway->providers($church),
            'gatewayConfigured' => collect($gateway->providers($church))->contains('configured', true),
        ]);
    }

    public function checkout(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $church = Church::query()->firstOrFail();
        $validated = $request->validate([
            'provider' => ['nullable', Rule::in(['stripe', 'paystack', 'paypal'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:999999.99', 'decimal:0,2'],
            'fund_id' => ['nullable', Rule::exists('funds', 'id')->where(fn ($query) => $query->where('church_id', $church->id)->where('is_active', true))],
            'campus_id' => ['nullable', Rule::exists('campuses', 'id')->where(fn ($query) => $query->where('church_id', $church->id)->where('status', 'active'))],
            'donor_name' => ['required', 'string', 'max:120'],
            'donor_email' => ['required', 'email', 'max:160'],
            'anonymous' => ['sometimes', 'accepted'],
        ]);
        $provider = $validated['provider'] ?? 'stripe';
        abort_unless($gateway->isConfigured($church, $provider), 503, 'The selected payment gateway is not configured.');
        $currency = $gateway->currency($church, $provider);

        $amount = round((float) $validated['amount'], 2);
        $transaction = PaymentGatewayTransaction::query()->create([
            'church_id' => $church->id,
            'campus_id' => $validated['campus_id'] ?? null,
            'member_id' => $request->boolean('anonymous') ? null : $request->user()?->member_id,
            'fund_id' => $validated['fund_id'] ?? null,
            'provider' => $provider,
            'reference' => 'PAY-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
            'status' => 'initiated',
            'amount' => $amount,
            'amount_minor' => (int) round($amount * 100),
            'currency' => $currency,
            'donor_name' => $request->boolean('anonymous') ? null : trim($validated['donor_name']),
            'donor_email' => Str::lower(trim($validated['donor_email'])),
            'metadata' => [
                'anonymous' => $request->boolean('anonymous'),
                'initiated_ip' => $request->ip(),
            ],
        ]);

        try {
            $successUrl = match ($provider) {
                'stripe' => route('giving.success').'?session_id={CHECKOUT_SESSION_ID}',
                'paystack' => route('giving.success'),
                'paypal' => route('giving.success'),
            };
            $checkout = $gateway->createCheckout(
                $transaction->load(['church', 'fund']),
                $successUrl,
                route('giving.cancel', ['reference' => $transaction->reference]),
            );
            $transaction->update(['provider_session_id' => $checkout['id']]);
        } catch (Throwable $exception) {
            report($exception);
            $transaction->update(['status' => 'failed']);

            return back()->withInput()->withErrors(['payment' => 'The secure checkout could not be started. Please try again.']);
        }

        return redirect()->away($checkout['url']);
    }

    public function success(Request $request, PaymentRecorder $recorder): View
    {
        $sessionId = $request->string('session_id')->toString()
            ?: $request->string('reference')->toString()
            ?: $request->string('token')->toString();
        abort_if($sessionId === '', 404);

        try {
            $transaction = $recorder->fulfill($sessionId)->load(['church', 'fund', 'donation']);
        } catch (Throwable $exception) {
            report($exception);
            $transaction = PaymentGatewayTransaction::query()->where('provider_session_id', $sessionId)->with('church')->firstOrFail();
        }

        return view('finance.giving-result', ['transaction' => $transaction]);
    }

    public function cancel(Request $request): View
    {
        $transaction = PaymentGatewayTransaction::query()
            ->where('reference', $request->string('reference')->toString())
            ->with('church')
            ->firstOrFail();

        if ($transaction->status === 'initiated') {
            $transaction->update(['status' => 'cancelled']);
        }

        return view('finance.giving-result', ['transaction' => $transaction->refresh()]);
    }
}
