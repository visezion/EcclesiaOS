<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\ActivityLog;
use App\Models\Donation;
use App\Models\PaymentGatewayTransaction;
use Illuminate\Support\Facades\DB;

final class PaymentRecorder
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function fulfill(string $sessionId): PaymentGatewayTransaction
    {
        $verified = $this->gateway->retrievePayment($sessionId);

        return DB::transaction(function () use ($sessionId, $verified): PaymentGatewayTransaction {
            $transaction = PaymentGatewayTransaction::query()
                ->where('provider_session_id', $sessionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status === 'paid' && $transaction->donation_id) {
                return $transaction;
            }

            if ($verified['status'] !== 'paid') {
                $transaction->update(['status' => $verified['status']]);

                return $transaction->refresh();
            }

            if ($verified['amount_minor'] !== $transaction->amount_minor || $verified['currency'] !== $transaction->currency) {
                $transaction->update(['status' => 'amount_mismatch']);

                return $transaction->refresh();
            }

            $donation = Donation::query()->firstOrCreate(
                ['reference' => strtoupper($transaction->provider).'-'.$verified['payment_id']],
                [
                    'church_id' => $transaction->church_id,
                    'campus_id' => $transaction->campus_id,
                    'member_id' => $transaction->member_id,
                    'fund_id' => $transaction->fund_id,
                    'amount' => $transaction->amount,
                    'currency' => $verified['currency'],
                    'method' => 'online',
                    'giving_source' => $transaction->member_id ? 'member' : 'anonymous',
                    'giving_frequency' => 'one_time',
                    'received_at' => $verified['paid_at'],
                    'notes' => 'Verified '.str($transaction->provider)->headline().' payment. Gateway reference: '.$transaction->reference,
                ],
            );

            $transaction->update([
                'donation_id' => $donation->id,
                'provider_payment_id' => $verified['payment_id'],
                'status' => 'paid',
                'paid_at' => $verified['paid_at'],
            ]);

            ActivityLog::query()->create([
                'church_id' => $transaction->church_id,
                'campus_id' => $transaction->campus_id,
                'subject_type' => $donation->getMorphClass(),
                'subject_id' => $donation->id,
                'module' => 'Finance',
                'action' => 'online_donation_paid',
                'description' => 'Online donation '.$donation->reference.' was verified and recorded.',
                'properties' => [
                    'provider' => $transaction->provider,
                    'gateway_reference' => $transaction->reference,
                    'amount' => $donation->amount,
                    'currency' => $donation->currency,
                ],
            ]);

            return $transaction->refresh();
        });
    }
}
