<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\ActivityLog;
use App\Models\Donation;
use App\Models\PaymentGatewayTransaction;
use App\Models\User;
use App\Services\Communications\DomainNotificationService;
use Illuminate\Support\Facades\DB;

final class PaymentRecorder
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly DomainNotificationService $domainNotifications,
    ) {}

    public function fulfill(string $sessionId): PaymentGatewayTransaction
    {
        $verified = $this->gateway->retrievePayment($sessionId);

        [$transaction, $changed] = DB::transaction(function () use ($sessionId, $verified): array {
            $transaction = PaymentGatewayTransaction::query()
                ->where('provider_session_id', $sessionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status === 'paid' && $transaction->donation_id) {
                return [$transaction, false];
            }

            if ($verified['status'] !== 'paid') {
                $transaction->update(['status' => $verified['status']]);

                return [$transaction->refresh(), true];
            }

            if ($verified['amount_minor'] !== $transaction->amount_minor || $verified['currency'] !== $transaction->currency) {
                $transaction->update(['status' => 'amount_mismatch']);

                return [$transaction->refresh(), true];
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

            return [$transaction->refresh(), true];
        });

        if ($changed) {
            $this->notifyPaymentResult($transaction);
        }

        return $transaction;
    }

    private function notifyPaymentResult(PaymentGatewayTransaction $transaction): void
    {
        $transaction->loadMissing('member.userAccount');
        $subject = $transaction->status === 'paid' ? 'Giving payment received' : 'Giving payment needs attention';
        $message = $transaction->status === 'paid'
            ? 'Thank you. Your '.number_format((float) $transaction->amount, 2).' '.$transaction->currency.' gift was recorded successfully.'
            : 'Your giving payment is currently '.str($transaction->status)->headline().'. No donation was recorded.';
        $metadata = ['url' => route('giving.success', ['session_id' => $transaction->provider_session_id])];

        if ($transaction->member) {
            $this->domainNotifications->member($transaction->member, 'PaymentStatusChanged', 'system', $subject, $message, ['in_app', 'email'], $metadata, true);
        } else {
            $user = User::query()
                ->where('church_id', $transaction->church_id)
                ->whereRaw('LOWER(email) = ?', [strtolower((string) $transaction->donor_email)])
                ->first();
            if ($user) {
                $this->domainNotifications->user($user, 'PaymentStatusChanged', 'system', $subject, $message, ['in_app', 'email'], $metadata, true);
            } else {
                $this->domainNotifications->contact(
                    (int) $transaction->church_id,
                    $transaction->donor_name ?: 'Donor',
                    $transaction->donor_email,
                    null,
                    'PaymentStatusChanged',
                    'system',
                    $subject,
                    $message,
                    ['email'],
                    $metadata,
                    true,
                );
            }
        }

        $financeUsers = User::query()
            ->where('church_id', $transaction->church_id)
            ->where('status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->whereIn('name', ['manage finance', 'view finance']))
            ->get();
        $this->domainNotifications->users(
            $financeUsers,
            'PaymentStatusChanged',
            'system',
            $subject,
            ($transaction->donor_name ?: 'A donor').' payment '.$transaction->reference.' is '.str($transaction->status)->headline().'.',
            ['in_app'],
            ['url' => route('finance.index')],
            $transaction->status !== 'paid',
        );
    }
}
