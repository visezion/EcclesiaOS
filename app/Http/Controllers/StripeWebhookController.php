<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\PaymentGatewayTransaction;
use App\Services\PaymentRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, PaymentRecorder $recorder, string $provider = 'stripe'): JsonResponse
    {
        try {
            abort_unless(in_array($provider, ['stripe', 'paystack', 'paypal'], true), 404);
            $signature = $provider === 'paystack'
                ? (string) $request->header('X-Paystack-Signature')
                : (string) $request->header('Stripe-Signature');
            $event = $gateway->verifyWebhook($request->getContent(), $signature, $provider, $request->headers->all());

            if (in_array($event['type'], [
                'checkout.session.completed',
                'checkout.session.async_payment_succeeded',
                'charge.success',
                'PAYMENT.CAPTURE.COMPLETED',
            ], true)) {
                $recorder->fulfill($event['session_id']);
            } elseif ($event['type'] === 'checkout.session.async_payment_failed') {
                PaymentGatewayTransaction::query()
                    ->where('provider_session_id', $event['session_id'])
                    ->where('status', '!=', 'paid')
                    ->update(['status' => 'failed']);
            }
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['received' => false], 400);
        }

        return response()->json(['received' => true]);
    }
}
