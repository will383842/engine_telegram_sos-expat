<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OnboardingLink;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OnboardingService
{
    public function __construct(
        protected FirestoreService $firestoreService,
    ) {}

    /**
     * Generate a deep link for Telegram onboarding.
     *
     * @return array{code: string, deepLink: string, expiresAt: string}
     */
    public function generateLink(string $userId, string $role): array
    {
        $expiryHours = (int) config('telegram.onboarding.link_expiry_hours', 24);
        $botUsername = $this->getBotUsername();

        // Invalidate any existing pending links for this user
        OnboardingLink::where('user_id', $userId)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        // Generate unique code
        $code = Str::upper(Str::random(8));

        $deepLink = "https://t.me/{$botUsername}?start={$code}";
        $expiresAt = now()->addHours($expiryHours);

        OnboardingLink::create([
            'code' => $code,
            'user_id' => $userId,
            'role' => $role,
            'status' => 'pending',
            'deep_link' => $deepLink,
            'expires_at' => $expiresAt,
        ]);

        return [
            'code' => $code,
            'deepLink' => $deepLink,
            'expiresAt' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Check the onboarding status for a user.
     *
     * @return array{status: string, isLinked: bool, telegramId: ?int, telegramUsername: ?string, code: ?string, deepLink: ?string}
     */
    public function checkStatus(string $userId): array
    {
        $link = OnboardingLink::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->first();

        if (!$link) {
            return [
                'status' => 'none',
                'isLinked' => false,
                'telegramId' => null,
                'telegramUsername' => null,
                'code' => null,
                'deepLink' => null,
            ];
        }

        // Check if expired
        if ($link->status === 'pending' && $link->expires_at && Carbon::parse($link->expires_at)->isPast()) {
            $link->update(['status' => 'expired']);
        }

        return [
            'status' => $link->status,
            'isLinked' => $link->status === 'linked',
            'telegramId' => $link->telegram_id,
            'telegramUsername' => $link->telegram_username,
            'code' => $link->code,
            'deepLink' => $link->deep_link,
        ];
    }

    /**
     * Handle a /start command from the Telegram bot.
     *
     * Links the Telegram account to the user's onboarding link.
     */
    public function handleBotStart(
        int $telegramId,
        string $telegramUsername,
        string $firstName,
        string $lastName,
        string $code,
    ): bool {
        $link = OnboardingLink::where('code', $code)
            ->where('status', 'pending')
            ->first();

        if (!$link) {
            Log::warning('OnboardingService: Invalid or expired onboarding code', [
                'code' => $code,
                'telegram_id' => $telegramId,
            ]);
            return false;
        }

        // Check expiry
        if ($link->expires_at && Carbon::parse($link->expires_at)->isPast()) {
            $link->update(['status' => 'expired']);
            Log::warning('OnboardingService: Expired onboarding code', ['code' => $code]);
            return false;
        }

        // Link Telegram account
        $link->update([
            'status' => 'linked',
            'telegram_id' => $telegramId,
            'telegram_username' => $telegramUsername,
            'telegram_first_name' => $firstName,
            'telegram_last_name' => $lastName,
            'linked_at' => now(),
        ]);

        // Sync to Firestore: update user document with telegram_id
        try {
            $this->firestoreService->setDocument('users', $link->user_id, [
                'telegram_id' => $telegramId,
                'telegram_username' => $telegramUsername,
                'telegramLinked' => true,
                'telegramLinkedAt' => now()->toIso8601String(),
            ], merge: true);
        } catch (\Throwable $e) {
            Log::error('OnboardingService: Failed to sync to Firestore', [
                'user_id' => $link->user_id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the whole operation — local link is saved
        }

        Log::info('OnboardingService: Telegram linked successfully', [
            'user_id' => $link->user_id,
            'telegram_id' => $telegramId,
            'role' => $link->role,
        ]);

        return true;
    }

    /**
     * Skip Telegram onboarding for a user.
     */
    public function skip(string $userId): void
    {
        // Expire all pending links
        OnboardingLink::where('user_id', $userId)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        // Sync skip to Firestore
        try {
            $this->firestoreService->setDocument('users', $userId, [
                'telegramOnboardingSkipped' => true,
                'telegramOnboardingSkippedAt' => now()->toIso8601String(),
            ], merge: true);
        } catch (\Throwable $e) {
            Log::error('OnboardingService: Failed to sync skip to Firestore', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the bot username from config or by calling getMe.
     */
    protected function getBotUsername(): string
    {
        $cached = cache()->remember('telegram_bot_username', 3600, function () {
            try {
                $botService = app(TelegramBotService::class);
                $info = $botService->validateBot();
                return $info['username'] ?? 'SOSExpatBot';
            } catch (\Throwable) {
                return 'SOSExpatBot';
            }
        });

        return $cached;
    }
}
