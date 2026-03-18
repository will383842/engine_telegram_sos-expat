<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Checks if contacts are already registered on SOS-Expat
 * by querying Firestore collections: users, chatters, influencers, bloggers, group_admins.
 */
class SosUserCheckService
{
    private FirestoreService $firestore;

    /** Collections to check for affiliate roles */
    private const AFFILIATE_COLLECTIONS = [
        'chatters' => 'chatter',
        'influencers' => 'influencer',
        'bloggers' => 'blogger',
        'group_admins' => 'group_admin',
    ];

    public function __construct(FirestoreService $firestore)
    {
        $this->firestore = $firestore;
    }

    /**
     * Check a single contact against SOS-Expat database.
     *
     * @return array{found:bool, uid?:string, role?:string, roles?:string[], name?:string, email?:string, checkedAt:string}
     */
    public function checkContact(?string $email, ?string $phone): array
    {
        $result = [
            'found' => false,
            'checkedAt' => now()->toISOString(),
        ];

        // 1. Search in users collection by email
        if ($email) {
            $emailLower = strtolower(trim($email));
            $users = $this->firestore->queryCollection('users', [
                ['field' => 'email', 'operator' => '==', 'value' => $emailLower],
            ], 1);

            if (empty($users)) {
                // Try emailLower field
                $users = $this->firestore->queryCollection('users', [
                    ['field' => 'emailLower', 'operator' => '==', 'value' => $emailLower],
                ], 1);
            }

            if (!empty($users)) {
                $user = $users[0];
                $result = $this->buildResult($user);
                return $result;
            }
        }

        // 2. Search by phone (if email didn't match)
        if ($phone) {
            $normalizedPhone = preg_replace('/[^0-9+]/', '', $phone);
            if (strlen($normalizedPhone) >= 8) {
                $users = $this->firestore->queryCollection('users', [
                    ['field' => 'phone', 'operator' => '==', 'value' => $normalizedPhone],
                ], 1);

                if (empty($users)) {
                    $users = $this->firestore->queryCollection('users', [
                        ['field' => 'phoneNumber', 'operator' => '==', 'value' => $normalizedPhone],
                    ], 1);
                }

                if (!empty($users)) {
                    $user = $users[0];
                    $result = $this->buildResult($user);
                    return $result;
                }
            }
        }

        return $result;
    }

    /**
     * Check multiple contacts in batch (optimized: groups by email).
     *
     * @param array<int, array{id:string, email?:string, phone?:string}> $contacts
     * @return array<string, array> Map of contact ID => SOS status
     */
    public function checkBatch(array $contacts): array
    {
        $results = [];

        foreach ($contacts as $contact) {
            $id = $contact['id'];
            $email = $contact['email'] ?? null;
            $phone = $contact['phone'] ?? null;

            try {
                $results[$id] = $this->checkContact($email, $phone);
            } catch (\Throwable $e) {
                Log::warning('SosUserCheckService: check failed for contact', [
                    'contact_id' => $id,
                    'error' => $e->getMessage(),
                ]);
                $results[$id] = [
                    'found' => false,
                    'error' => 'check_failed',
                    'checkedAt' => now()->toISOString(),
                ];
            }
        }

        return $results;
    }

    /**
     * Build a result array from a Firestore user document.
     */
    private function buildResult(array $user): array
    {
        $uid = $user['_id'] ?? $user['uid'] ?? null;
        $primaryRole = $user['role'] ?? 'unknown';
        $roles = [$primaryRole];

        // Check affiliate collections for additional roles
        if ($uid) {
            foreach (self::AFFILIATE_COLLECTIONS as $collection => $roleName) {
                try {
                    $doc = $this->firestore->getDocument($collection, $uid);
                    if ($doc !== null) {
                        $roles[] = $roleName;
                    }
                } catch (\Throwable $e) {
                    // Silently skip if collection check fails
                }
            }
        }

        $roles = array_values(array_unique($roles));

        return [
            'found' => true,
            'uid' => $uid,
            'role' => $primaryRole,
            'roles' => $roles,
            'name' => trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')),
            'email' => $user['email'] ?? null,
            'isApproved' => $user['isApproved'] ?? false,
            'createdAt' => isset($user['createdAt']) ? $this->formatTimestamp($user['createdAt']) : null,
            'checkedAt' => now()->toISOString(),
        ];
    }

    /**
     * Format a Firestore Timestamp to ISO string.
     */
    private function formatTimestamp(mixed $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        if ($timestamp instanceof \Google\Cloud\Core\Timestamp) {
            return $timestamp->get()->format('c');
        }

        if (is_string($timestamp)) {
            return $timestamp;
        }

        if (is_array($timestamp) && isset($timestamp['_seconds'])) {
            return date('c', $timestamp['_seconds']);
        }

        return null;
    }
}
