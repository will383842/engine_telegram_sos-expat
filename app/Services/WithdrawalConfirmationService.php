<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WithdrawalConfirmation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WithdrawalConfirmationService
{
    public function __construct(
        protected TelegramBotService $telegramBot,
        protected FirestoreService $firestoreService,
        protected TemplateRenderer $renderer,
    ) {}

    /**
     * Create a new withdrawal confirmation and send inline keyboard to user.
     */
    public function createConfirmation(
        string $withdrawalId,
        string $userId,
        string $chatId,
        int $amountCents,
        string $paymentMethod,
    ): WithdrawalConfirmation {
        $expiryMinutes = (int) config('telegram.withdrawal.expiry_minutes', 15);

        // Cancel any existing pending confirmations for this withdrawal
        WithdrawalConfirmation::where('withdrawal_id', $withdrawalId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        // Generate unique confirmation code
        $code = Str::upper(Str::random(6));

        $confirmation = WithdrawalConfirmation::create([
            'code' => $code,
            'withdrawal_id' => $withdrawalId,
            'user_id' => $userId,
            'chat_id' => $chatId,
            'amount_cents' => $amountCents,
            'payment_method' => $paymentMethod,
            'status' => 'pending',
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        // Send inline keyboard via Telegram
        $formattedAmount = $this->renderer->formatMoney($amountCents);

        $message = "<b>Confirmation de retrait</b>\n\n"
            . "Montant: <b>{$formattedAmount}</b>\n"
            . "Methode: <b>{$paymentMethod}</b>\n"
            . "Code: <code>{$code}</code>\n\n"
            . "Expire dans {$expiryMinutes} minutes.\n"
            . "Confirmez-vous ce retrait ?";

        $buttons = [
            ['text' => 'Confirmer', 'callback_data' => "withdraw_confirm:{$code}"],
            ['text' => 'Annuler', 'callback_data' => "withdraw_cancel:{$withdrawalId}"],
        ];

        $this->telegramBot->sendInlineKeyboard($chatId, $message, $buttons);

        return $confirmation;
    }

    /**
     * Get the status of a withdrawal confirmation.
     *
     * @return array{status: string, code: string, amountCents: int, expiresAt: string, isExpired: bool}
     */
    public function getStatus(string $withdrawalId): array
    {
        $confirmation = WithdrawalConfirmation::where('withdrawal_id', $withdrawalId)
            ->orderByDesc('created_at')
            ->first();

        if (!$confirmation) {
            return [
                'status' => 'not_found',
                'code' => '',
                'amountCents' => 0,
                'expiresAt' => '',
                'isExpired' => true,
            ];
        }

        $isExpired = $confirmation->status === 'pending'
            && $confirmation->expires_at
            && Carbon::parse($confirmation->expires_at)->isPast();

        if ($isExpired && $confirmation->status === 'pending') {
            $confirmation->update(['status' => 'expired']);
        }

        return [
            'status' => $confirmation->status,
            'code' => $confirmation->code,
            'amountCents' => $confirmation->amount_cents,
            'expiresAt' => $confirmation->expires_at?->toIso8601String() ?? '',
            'isExpired' => $isExpired,
        ];
    }

    /**
     * Confirm a withdrawal by code.
     */
    public function confirm(string $code): bool
    {
        $confirmation = WithdrawalConfirmation::where('code', $code)
            ->where('status', 'pending')
            ->first();

        if (!$confirmation) {
            Log::warning('WithdrawalConfirmationService: Invalid code', ['code' => $code]);
            return false;
        }

        // Check expiry
        if ($confirmation->expires_at && Carbon::parse($confirmation->expires_at)->isPast()) {
            $confirmation->update(['status' => 'expired']);
            Log::warning('WithdrawalConfirmationService: Expired confirmation', ['code' => $code]);
            return false;
        }

        $confirmation->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        // Sync confirmation to Firestore
        try {
            $this->firestoreService->setDocument('withdrawal_requests', $confirmation->withdrawal_id, [
                'telegramConfirmed' => true,
                'telegramConfirmedAt' => now()->toIso8601String(),
                'telegramConfirmationCode' => $code,
            ], merge: true);
        } catch (\Throwable $e) {
            Log::error('WithdrawalConfirmationService: Failed to sync to Firestore', [
                'withdrawal_id' => $confirmation->withdrawal_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Send confirmation message
        $formattedAmount = $this->renderer->formatMoney($confirmation->amount_cents);
        $this->telegramBot->sendMessage(
            $confirmation->chat_id,
            "Retrait de <b>{$formattedAmount}</b> confirme. Votre demande est en cours de traitement.",
        );

        Log::info('WithdrawalConfirmationService: Withdrawal confirmed', [
            'withdrawal_id' => $confirmation->withdrawal_id,
            'user_id' => $confirmation->user_id,
        ]);

        return true;
    }

    /**
     * Cancel a withdrawal confirmation.
     */
    public function cancel(string $withdrawalId): bool
    {
        $confirmation = WithdrawalConfirmation::where('withdrawal_id', $withdrawalId)
            ->where('status', 'pending')
            ->first();

        if (!$confirmation) {
            return false;
        }

        $confirmation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        // Sync cancellation to Firestore
        try {
            $this->firestoreService->setDocument('withdrawal_requests', $withdrawalId, [
                'telegramCancelled' => true,
                'telegramCancelledAt' => now()->toIso8601String(),
            ], merge: true);
        } catch (\Throwable $e) {
            Log::error('WithdrawalConfirmationService: Failed to sync cancel to Firestore', [
                'withdrawal_id' => $withdrawalId,
                'error' => $e->getMessage(),
            ]);
        }

        // Notify user
        $formattedAmount = $this->renderer->formatMoney($confirmation->amount_cents);
        $this->telegramBot->sendMessage(
            $confirmation->chat_id,
            "Retrait de <b>{$formattedAmount}</b> annule.",
        );

        return true;
    }

    /**
     * Clean up expired confirmations.
     *
     * @return int Number of confirmations expired.
     */
    public function cleanupExpired(): int
    {
        $expired = WithdrawalConfirmation::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $confirmation) {
            $confirmation->update(['status' => 'expired']);

            // Notify user
            $this->telegramBot->sendMessage(
                $confirmation->chat_id,
                "Votre confirmation de retrait a expire. Veuillez refaire une demande.",
            );

            $count++;
        }

        return $count;
    }
}
