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
            'userId' => 'required|string|max:128',
            'role'   => 'required|string|in:chatter,influencer,blogger,group_admin',
        ]);

        $result = $this->onboardingService->generateLink(
            $validated['userId'],
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
