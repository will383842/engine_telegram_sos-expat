<?php

declare(strict_types=1);

namespace App\Services;

use Google\Cloud\Core\Timestamp;
use Google\Cloud\Firestore\FieldValue;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

/**
 * Handles withdrawal confirmation callbacks from Telegram (wc_confirm_ / wc_cancel_).
 *
 * This mirrors the exact Firestore logic from Firebase's withdrawalConfirmation.ts
 * so that Laravel can process callbacks sent by Firebase's inline keyboard.
 *
 * Firestore collections involved:
 * - telegram_withdrawal_confirmations/{code}
 * - payment_withdrawals/{id} | group_admin_withdrawals/{id} | affiliate_payouts/{id}
 * - chatters/{userId} | influencers/{userId} | bloggers/{userId} | etc.
 * - affiliate_commissions/{id} (for affiliate role cancellation)
 */
class FirebaseWithdrawalCallbackService
{
    /**
     * Role → Firestore collection for balance refunds.
     */
    private const ROLE_COLLECTIONS = [
        'chatter'        => 'chatters',
        'influencer'     => 'influencers',
        'blogger'        => 'bloggers',
        'groupAdmin'     => 'group_admins',
        'affiliate'      => 'users',
        'client'         => 'users',
        'lawyer'         => 'users',
        'expat'          => 'users',
        'captainChatter' => 'captain_chatters',
        'captain_chatter' => 'captain_chatters',
        'partner'        => 'partners',
    ];

    public function __construct(
        protected FirestoreService $firestoreService,
        protected TelegramBotService $telegramBotService,
    ) {}

    /**
     * Handle a wc_confirm_{code} or wc_cancel_{code} callback from Telegram.
     *
     * @param array $callbackQuery The raw callback_query from Telegram update
     * @param Nutgram $bot The bot instance to respond with
     */
    public function handleCallback(array $callbackQuery, Nutgram $bot): void
    {
        $data      = $callbackQuery['data'] ?? '';
        $chatId    = (string) ($callbackQuery['message']['chat']['id'] ?? '');
        $messageId = (int) ($callbackQuery['message']['message_id'] ?? 0);
        $fromId    = (int) ($callbackQuery['from']['id'] ?? 0);
        $callbackId = $callbackQuery['id'] ?? '';

        // Parse wc_confirm_{code} or wc_cancel_{code}
        if (!preg_match('/^wc_(confirm|cancel)_(.+)$/', $data, $matches)) {
            $this->telegramBotService->answerCallbackQueryWithBot($bot, $callbackId, 'Action invalide', true);
            return;
        }

        $action = $matches[1]; // "confirm" or "cancel"
        $code   = $matches[2];

        // Read confirmation doc from Firestore
        $confirmation = $this->firestoreService->getDocument('telegram_withdrawal_confirmations', $code);

        if (!$confirmation) {
            $this->telegramBotService->answerCallbackQueryWithBot($bot, $callbackId, 'Demande introuvable ou expiree', true);
            return;
        }

        // Security: verify Telegram user matches
        $expectedTelegramId = (int) ($confirmation['telegramId'] ?? 0);
        if ($fromId !== $expectedTelegramId) {
            Log::warning('FirebaseWithdrawalCallback: Telegram ID mismatch', [
                'expected' => $expectedTelegramId,
                'got'      => $fromId,
                'code'     => $code,
            ]);
            $this->telegramBotService->answerCallbackQueryWithBot($bot, $callbackId, 'Non autorise', true);
            return;
        }

        // Check not already resolved
        $status = $confirmation['status'] ?? '';
        if ($status !== 'pending') {
            $this->telegramBotService->answerCallbackQueryWithBot($bot, $callbackId, "Deja {$status}", true);
            return;
        }

        // Check expiry (expiresAt is a Firestore Timestamp)
        $expiresAt = $confirmation['expiresAt'] ?? null;
        $isExpired = false;
        if ($expiresAt instanceof Timestamp) {
            $isExpired = $expiresAt->get()->getTimestamp() < time();
        } elseif ($expiresAt instanceof \DateTimeInterface) {
            $isExpired = $expiresAt->getTimestamp() < time();
        }

        if ($isExpired) {
            $this->handleExpiry($code, $confirmation, $bot, $callbackId, $chatId, $messageId);
            return;
        }

        $amountFormatted = number_format(($confirmation['amount'] ?? 0) / 100, 2);
        $paymentMethod   = $confirmation['paymentMethod'] ?? '';

        if ($action === 'confirm') {
            $this->handleConfirm($code, $confirmation, $amountFormatted, $paymentMethod, $bot, $callbackId, $chatId, $messageId);
        } else {
            $this->handleCancel($code, $confirmation, $amountFormatted, $bot, $callbackId, $chatId, $messageId);
        }
    }

    /**
     * Handle confirm action — update confirmation + withdrawal docs atomically.
     */
    private function handleConfirm(
        string $code,
        array $confirmation,
        string $amountFormatted,
        string $paymentMethod,
        Nutgram $bot,
        string $callbackId,
        string $chatId,
        int $messageId,
    ): void {
        try {
            $collection   = $confirmation['collection'] ?? 'payment_withdrawals';
            $withdrawalId = $confirmation['withdrawalId'] ?? '';

            $this->firestoreService->runTransaction(function ($transaction) use ($code, $collection, $withdrawalId) {
                $confirmRef    = $this->firestoreService->getDocumentReference('telegram_withdrawal_confirmations', $code);
                $withdrawalRef = $this->firestoreService->getDocumentReference($collection, $withdrawalId);

                $now = new Timestamp(new \DateTime());

                $transaction->update($confirmRef, [
                    ['path' => 'status', 'value' => 'confirmed'],
                    ['path' => 'resolvedAt', 'value' => $now],
                ]);

                $transaction->update($withdrawalRef, [
                    ['path' => 'telegramConfirmationPending', 'value' => false],
                    ['path' => 'telegramConfirmedAt', 'value' => $now],
                ]);
            });

            $this->telegramBotService->answerCallbackQueryWithBot($bot, $callbackId, 'Retrait confirme !');

            if ($chatId && $messageId) {
                $this->telegramBotService->editMessageTextWithBot(
                    $bot,
                    $chatId,
                    $messageId,
                    "✅ <b>Retrait confirme !</b>\n\nVotre retrait de <b>\${$amountFormatted}</b> via <b>{$paymentMethod}</b> a ete confirme.\n\nIl sera traite dans les plus brefs delais.",
                );
            }

            Log::info('FirebaseWithdrawalCallback: Withdrawal confirmed', [
                'code'          => $code,
                'withdrawalId'  => $withdrawalId,
            ]);
        } catch (\Throwable $e) {
            Log::error('FirebaseWithdrawalCallback: Confirm failed', [
                'code'  => $code,
                'error' => $e->getMessage(),
            ]);
            $this->telegramBotService->answerCallbackQueryWithBot($bot, $callbackId, 'Erreur technique, reessayez', true);
        }
    }

    /**
     * Handle cancel action — cancel withdrawal, refund balance, restore commissions.
     */
    private function handleCancel(
        string $code,
        array $confirmation,
        string $amountFormatted,
        Nutgram $bot,
        string $callbackId,
        string $chatId,
        int $messageId,
    ): void {
        try {
            $collection   = $confirmation['collection'] ?? 'payment_withdrawals';
            $withdrawalId = $confirmation['withdrawalId'] ?? '';
            $role         = $confirmation['role'] ?? '';
            $userId       = $confirmation['userId'] ?? '';
            $amount       = (int) ($confirmation['amount'] ?? 0);

            $this->firestoreService->runTransaction(function ($transaction) use (
                $code, $collection, $withdrawalId, $role, $userId, $amount
            ) {
                $confirmRef    = $this->firestoreService->getDocumentReference('telegram_withdrawal_confirmations', $code);
                $withdrawalRef = $this->firestoreService->getDocumentReference($collection, $withdrawalId);

                // Read withdrawal first (Firestore requires reads before writes in transactions)
                $withdrawalSnap = $transaction->snapshot($withdrawalRef);

                if (!$withdrawalSnap->exists()) {
                    throw new \RuntimeException('Withdrawal not found');
                }

                $withdrawalData   = $withdrawalSnap->data();
                $withdrawalStatus = $withdrawalData['status'] ?? '';

                if ($withdrawalStatus !== 'pending' && $withdrawalStatus !== 'approved') {
                    throw new \RuntimeException("Cannot cancel withdrawal with status: {$withdrawalStatus}");
                }

                $now          = new Timestamp(new \DateTime());
                $nowIso       = (new \DateTime())->format('c');
                $refundAmount = $withdrawalData['totalDebited'] ?? $amount;

                // Update confirmation
                $transaction->update($confirmRef, [
                    ['path' => 'status', 'value' => 'cancelled'],
                    ['path' => 'resolvedAt', 'value' => $now],
                ]);

                // Update withdrawal
                $transaction->update($withdrawalRef, [
                    ['path' => 'status', 'value' => 'cancelled'],
                    ['path' => 'cancelledAt', 'value' => $nowIso],
                    ['path' => 'telegramConfirmationPending', 'value' => false],
                    ['path' => 'statusHistory', 'value' => FieldValue::arrayUnion([[
                        'status'    => 'cancelled',
                        'timestamp' => $nowIso,
                        'actorType' => 'user',
                        'note'      => 'Cancelled via Telegram',
                    ]])],
                ]);

                // Refund balance based on role
                if ($role === 'affiliate') {
                    $userRef = $this->firestoreService->getDocumentReference('users', $userId);
                    $transaction->update($userRef, [
                        ['path' => 'availableBalance', 'value' => FieldValue::increment($refundAmount)],
                        ['path' => 'pendingPayoutId', 'value' => null],
                        ['path' => 'updatedAt', 'value' => $now],
                    ]);

                    // Restore affiliate commissions to "available"
                    $commissionIds = $withdrawalData['commissionIds'] ?? [];
                    if (is_array($commissionIds)) {
                        foreach ($commissionIds as $commissionId) {
                            $commRef = $this->firestoreService->getDocumentReference('affiliate_commissions', $commissionId);
                            $transaction->update($commRef, [
                                ['path' => 'status', 'value' => 'available'],
                                ['path' => 'payoutId', 'value' => null],
                                ['path' => 'paidAt', 'value' => null],
                                ['path' => 'updatedAt', 'value' => $now],
                            ]);
                        }
                    }
                } elseif ($role === 'groupAdmin') {
                    $gaRef = $this->firestoreService->getDocumentReference('group_admins', $userId);
                    $transaction->update($gaRef, [
                        ['path' => 'availableBalance', 'value' => FieldValue::increment($refundAmount)],
                        ['path' => 'pendingWithdrawalId', 'value' => null],
                        ['path' => 'updatedAt', 'value' => $now],
                    ]);
                } else {
                    // chatter, influencer, blogger, captain, partner, client, lawyer, expat
                    $col = self::ROLE_COLLECTIONS[$role] ?? null;
                    if ($col) {
                        $roleRef = $this->firestoreService->getDocumentReference($col, $userId);
                        $transaction->update($roleRef, [
                            ['path' => 'availableBalance', 'value' => FieldValue::increment($refundAmount)],
                            ['path' => 'pendingWithdrawalId', 'value' => null],
                            ['path' => 'updatedAt', 'value' => $now],
                        ]);
                    }
                }
            });

            $this->telegramBotService->answerCallbackQueryWithBot($bot, $callbackId, 'Retrait annule');

            if ($chatId && $messageId) {
                $this->telegramBotService->editMessageTextWithBot(
                    $bot,
                    $chatId,
                    $messageId,
                    "❌ <b>Retrait annule</b>\n\nVotre demande de retrait de <b>\${$amountFormatted}</b> a ete annulee.\nLe montant a ete recredite a votre solde.",
                );
            }

            Log::info('FirebaseWithdrawalCallback: Withdrawal cancelled', [
                'code'          => $code,
                'withdrawalId'  => $withdrawalId,
            ]);
        } catch (\Throwable $e) {
            Log::error('FirebaseWithdrawalCallback: Cancel failed', [
                'code'  => $code,
                'error' => $e->getMessage(),
            ]);
            $this->telegramBotService->answerCallbackQueryWithBot($bot, $callbackId, 'Erreur technique, reessayez', true);
        }
    }

    /**
     * Handle expired confirmation — cancel withdrawal and refund balance.
     */
    private function handleExpiry(
        string $code,
        array $confirmation,
        Nutgram $bot,
        string $callbackId,
        string $chatId,
        int $messageId,
    ): void {
        try {
            $collection   = $confirmation['collection'] ?? 'payment_withdrawals';
            $withdrawalId = $confirmation['withdrawalId'] ?? '';
            $role         = $confirmation['role'] ?? '';
            $userId       = $confirmation['userId'] ?? '';
            $amount       = (int) ($confirmation['amount'] ?? 0);

            $this->firestoreService->runTransaction(function ($transaction) use (
                $code, $collection, $withdrawalId, $role, $userId, $amount
            ) {
                $confirmRef    = $this->firestoreService->getDocumentReference('telegram_withdrawal_confirmations', $code);
                $withdrawalRef = $this->firestoreService->getDocumentReference($collection, $withdrawalId);

                $withdrawalSnap = $transaction->snapshot($withdrawalRef);

                $now    = new Timestamp(new \DateTime());
                $nowIso = (new \DateTime())->format('c');

                // Update confirmation to expired
                $transaction->update($confirmRef, [
                    ['path' => 'status', 'value' => 'expired'],
                    ['path' => 'resolvedAt', 'value' => $now],
                ]);

                // Cancel withdrawal and refund if still pending/approved
                if ($withdrawalSnap->exists()) {
                    $wData  = $withdrawalSnap->data();
                    $wStatus = $wData['status'] ?? '';

                    if ($wStatus === 'pending' || $wStatus === 'approved') {
                        $refundAmount = $wData['totalDebited'] ?? $amount;

                        $transaction->update($withdrawalRef, [
                            ['path' => 'status', 'value' => 'cancelled'],
                            ['path' => 'cancelledAt', 'value' => $nowIso],
                            ['path' => 'telegramConfirmationPending', 'value' => false],
                            ['path' => 'statusHistory', 'value' => FieldValue::arrayUnion([[
                                'status'    => 'cancelled',
                                'timestamp' => $nowIso,
                                'actorType' => 'system',
                                'note'      => 'Auto-cancelled: Telegram confirmation expired',
                            ]])],
                        ]);

                        // Refund balance
                        $col = self::ROLE_COLLECTIONS[$role] ?? null;
                        if ($col) {
                            $roleRef = $this->firestoreService->getDocumentReference($col, $userId);
                            $transaction->update($roleRef, [
                                ['path' => 'availableBalance', 'value' => FieldValue::increment($refundAmount)],
                                ['path' => 'pendingWithdrawalId', 'value' => null],
                                ['path' => 'updatedAt', 'value' => $now],
                            ]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('FirebaseWithdrawalCallback: Expiry cancellation failed', [
                'code'  => $code,
                'error' => $e->getMessage(),
            ]);
        }

        $this->telegramBotService->answerCallbackQueryWithBot($bot, $callbackId, 'Demande expiree - retrait annule', true);

        if ($chatId && $messageId) {
            $this->telegramBotService->editMessageTextWithBot(
                $bot,
                $chatId,
                $messageId,
                "⏰ <b>Demande expiree</b>\n\nCette demande de retrait a expire.\nLe montant a ete recredite a votre solde.",
            );
        }
    }
}
