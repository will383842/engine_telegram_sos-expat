<?php

declare(strict_types=1);

namespace App\Services;

use Google\Cloud\Firestore\FirestoreClient;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Support\Facades\Log;

class FirestoreService
{
    protected ?FirestoreClient $firestore = null;

    /**
     * Get the Firestore client (lazy singleton).
     */
    protected function db(): FirestoreClient
    {
        if ($this->firestore === null) {
            $credentials = config('firebase.projects.app.credentials');
            $projectId = env('FIREBASE_PROJECT_ID', env('GOOGLE_CLOUD_PROJECT'));

            $keyFilePath = $credentials && file_exists($credentials)
                ? $credentials
                : (file_exists(base_path($credentials)) ? base_path($credentials) : null);

            $config = [];

            if ($keyFilePath) {
                $config['keyFilePath'] = $keyFilePath;
            }

            if ($projectId) {
                $config['projectId'] = $projectId;
            }

            $this->firestore = new FirestoreClient($config);
        }

        return $this->firestore;
    }

    /**
     * Get a single document from a collection.
     *
     * @return array<string, mixed>|null
     */
    public function getDocument(string $collection, string $docId): ?array
    {
        try {
            $snapshot = $this->db()->collection($collection)->document($docId)->snapshot();

            if (!$snapshot->exists()) {
                return null;
            }

            return $snapshot->data();
        } catch (\Throwable $e) {
            Log::error('FirestoreService::getDocument failed', [
                'collection' => $collection,
                'docId' => $docId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Set (create or merge) a document in a collection.
     */
    public function setDocument(string $collection, string $docId, array $data, bool $merge = true): void
    {
        try {
            $docRef = $this->db()->collection($collection)->document($docId);

            if ($merge) {
                $docRef->set($data, ['merge' => true]);
            } else {
                $docRef->set($data);
            }
        } catch (\Throwable $e) {
            Log::error('FirestoreService::setDocument failed', [
                'collection' => $collection,
                'docId' => $docId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Query a collection with optional where clauses and limit.
     *
     * @param array<int, array{field: string, operator: string, value: mixed}> $where
     * @return array<int, array<string, mixed>>
     */
    public function queryCollection(string $collection, array $where = [], ?int $limit = null): array
    {
        try {
            $query = $this->db()->collection($collection);

            foreach ($where as $condition) {
                $query = $query->where(
                    $condition['field'],
                    $condition['operator'],
                    $condition['value'],
                );
            }

            if ($limit !== null) {
                $query = $query->limit($limit);
            }

            $results = [];
            $documents = $query->documents();

            foreach ($documents as $document) {
                if ($document->exists()) {
                    $data = $document->data();
                    $data['_id'] = $document->id();
                    $results[] = $data;
                }
            }

            return $results;
        } catch (\Throwable $e) {
            Log::error('FirestoreService::queryCollection failed', [
                'collection' => $collection,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Run a Firestore transaction.
     *
     * @param callable $callback Receives the Transaction object
     * @return mixed The return value of the callback
     */
    public function runTransaction(callable $callback): mixed
    {
        return $this->db()->runTransaction($callback);
    }

    /**
     * Get a document reference (for use in transactions).
     */
    public function getDocumentReference(string $collection, string $docId): \Google\Cloud\Firestore\DocumentReference
    {
        return $this->db()->collection($collection)->document($docId);
    }

    /**
     * Delete a document from a collection.
     */
    public function deleteDocument(string $collection, string $docId): void
    {
        try {
            $this->db()->collection($collection)->document($docId)->delete();
        } catch (\Throwable $e) {
            Log::error('FirestoreService::deleteDocument failed', [
                'collection' => $collection,
                'docId' => $docId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
