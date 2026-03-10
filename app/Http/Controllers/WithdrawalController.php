<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WithdrawalConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalConfirmationService $withdrawalService,
    ) {}

    /**
     * Get the current status of a withdrawal confirmation.
     */
    public function getStatus(string $id): JsonResponse
    {
        $result = $this->withdrawalService->getStatus($id);

        if ($result['status'] === 'not_found') {
            return response()->json([
                'success' => false,
                'error'   => 'Withdrawal confirmation not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    /**
     * Cancel a pending withdrawal confirmation.
     */
    public function cancel(string $id, Request $request): JsonResponse
    {
        $success = $this->withdrawalService->cancel($id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'error'   => 'Unable to cancel. Withdrawal may have already been processed or expired.',
            ], 422);
        }

        return response()->json(['success' => true]);
    }
}
