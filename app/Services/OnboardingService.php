<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OnboardingLink;
use App\Models\TelegramGroup;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OnboardingService
{
    public function __construct(
        protected FirestoreService $firestoreService,
        protected TelegramGroupService $telegramGroupService,
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
     * Links the Telegram account, syncs to Firestore, and returns context
     * for sending a welcome message with the appropriate group link.
     *
     * @return array{success: bool, link: ?OnboardingLink, group: ?TelegramGroup, errorType: ?string, language: string}
     */
    public function handleBotStart(
        int $telegramId,
        string $telegramUsername,
        string $firstName,
        string $lastName,
        string $code,
    ): array {
        $link = OnboardingLink::where('code', $code)
            ->where('status', 'pending')
            ->first();

        if (!$link) {
            Log::warning('OnboardingService: Invalid or expired onboarding code', [
                'code' => $code,
                'telegram_id' => $telegramId,
            ]);
            return ['success' => false, 'link' => null, 'group' => null, 'errorType' => 'invalid', 'language' => 'en'];
        }

        // Check expiry
        if ($link->expires_at && Carbon::parse($link->expires_at)->isPast()) {
            $link->update(['status' => 'expired']);
            Log::warning('OnboardingService: Expired onboarding code', ['code' => $code]);
            return ['success' => false, 'link' => null, 'group' => null, 'errorType' => 'expired', 'language' => 'en'];
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

        // Fetch user data from Firestore to get country/language
        $userData = $this->fetchUserData($link->user_id, $link->role);
        $userCountry = $userData['country'] ?? '';
        $userLanguage = $userData['language'] ?? 'fr';

        // Find the right Telegram group
        $group = $this->telegramGroupService->findGroupForUser(
            $link->role,
            $userLanguage,
            $userCountry,
        );

        // Sync to Firestore: update user document with telegram_id + group info
        try {
            $firestoreData = [
                'telegram_id' => $telegramId,
                'telegram_username' => $telegramUsername,
                'telegramLinked' => true,
                'telegramLinkedAt' => now()->toIso8601String(),
            ];

            if ($group) {
                $firestoreData['telegramGroupId'] = $group->slug;
                $firestoreData['telegramGroupName'] = $group->name;
            }

            // Write to users collection
            $this->firestoreService->setDocument('users', $link->user_id, $firestoreData, merge: true);

            // Also write to role-specific collection
            $roleCollection = $this->getRoleCollection($link->role);
            if ($roleCollection && $roleCollection !== 'users') {
                $this->firestoreService->setDocument($roleCollection, $link->user_id, $firestoreData, merge: true);
            }
        } catch (\Throwable $e) {
            Log::error('OnboardingService: Failed to sync to Firestore', [
                'user_id' => $link->user_id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('OnboardingService: Telegram linked successfully', [
            'user_id' => $link->user_id,
            'telegram_id' => $telegramId,
            'role' => $link->role,
            'group' => $group?->slug,
        ]);

        return ['success' => true, 'link' => $link, 'group' => $group, 'errorType' => null, 'language' => $userLanguage];
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
     * Get the onboarding bot username from config.
     */
    protected function getBotUsername(): string
    {
        return config('telegram.onboarding_bot_username', 'sos_expat_onboarding_users_bot');
    }

    /**
     * Fetch user data from Firestore to determine country and language.
     *
     * @return array{country: string, language: string}
     */
    protected function fetchUserData(string $userId, string $role): array
    {
        try {
            // Try role-specific collection first, then users
            $collections = array_filter([
                $this->getRoleCollection($role),
                'users',
            ]);

            foreach (array_unique($collections) as $collection) {
                $data = $this->firestoreService->getDocument($collection, $userId);
                if ($data) {
                    return [
                        'country' => $data['country'] ?? $data['countryCode'] ?? '',
                        'language' => $data['language'] ?? $data['preferredLanguage'] ?? 'fr',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OnboardingService: Failed to fetch user data', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return ['country' => '', 'language' => 'fr'];
    }

    /**
     * Map role to Firestore collection name.
     */
    protected function getRoleCollection(string $role): ?string
    {
        return match ($role) {
            'chatter'         => 'chatters',
            'influencer'      => 'influencers',
            'blogger'         => 'bloggers',
            'group_admin'     => 'group_admins',
            'captain'         => 'captains',
            'captain_chatter' => 'captain_chatters',
            'partner'         => 'partners',
            'affiliate'       => 'users',
            'client', 'lawyer', 'expat' => 'users',
            default => null,
        };
    }
}
