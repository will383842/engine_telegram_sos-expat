<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
    ) {}

    /**
     * Generate a new onboarding deep link for a user.
     */
    public function generateLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|in:chatter,influencer,blogger,group_admin,client,lawyer,expat,partner,captain,captain_chatter,affiliate',
        ]);

        // Always use the authenticated Firebase UID, never trust client-sent userId
        $userId = $request->input('firebase_uid');

        $result = $this->onboardingService->generateLink(
            $userId,
            $validated['role'],
        );

        return response()->json([
            'success'   => true,
            'code'      => $result['code'],
            'deepLink'  => $result['deepLink'],
            'expiresAt' => $result['expiresAt'],
        ]);
    }

    /**
     * Check the status of an onboarding link.
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $result = $this->onboardingService->checkStatus(
            $request->input('firebase_uid'),
        );

        return response()->json([
            'success'          => true,
            'status'           => $result['status'],
            'isLinked'         => $result['isLinked'],
            'telegramId'       => $result['telegramId'] ?? null,
            'telegramUsername'  => $result['telegramUsername'] ?? null,
        ]);
    }

    /**
     * Skip onboarding (user opts out of Telegram linking).
     */
    public function skip(Request $request): JsonResponse
    {
        $this->onboardingService->skip(
            $request->input('firebase_uid'),
        );

        return response()->json(['success' => true]);
    }
}
