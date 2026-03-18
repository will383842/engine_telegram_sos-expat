<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\JobCategory;
use App\Models\JobContact;
use App\Services\CvAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class JobContactController extends Controller
{
    /* =====================================================================
     * CATEGORIES — CRUD
     * =================================================================== */

    public function listCategories(): JsonResponse
    {
        $categories = JobCategory::withCount('contacts')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function createCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Check for existing slug
        $existing = JobCategory::where('slug', $validated['slug'])->first();
        if ($existing) {
            return response()->json(['success' => true, 'data' => $existing, 'existed' => true]);
        }

        $category = JobCategory::create($validated);
        $category->contacts_count = 0;

        return response()->json(['success' => true, 'data' => $category], 201);
    }

    public function updateCategory(Request $request, string $id): JsonResponse
    {
        $category = JobCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json(['success' => true, 'data' => $category->fresh()->loadCount('contacts')]);
    }

    public function destroyCategory(string $id): JsonResponse
    {
        $category = JobCategory::findOrFail($id);

        // Nullify contacts' category_id before deleting
        JobContact::where('category_id', $id)->update(['category_id' => null]);
        $category->delete();

        return response()->json(['success' => true]);
    }

    /* =====================================================================
     * CONTACTS — CRUD
     * =================================================================== */

    public function listContacts(Request $request): JsonResponse
    {
        $query = JobContact::with('category');

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($sourceAdId = $request->input('source_ad_id')) {
            $query->where('source_ad_id', $sourceAdId);
        }
        if ($request->boolean('duplicates_only')) {
            $query->where('is_duplicate', true);
        }
        if ($search = $request->input('search')) {
            $search = Str::limit($search, 200);
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'ilike', "%{$search}%")
                  ->orWhere('current_position', 'ilike', "%{$search}%")
                  ->orWhere('city', 'ilike', "%{$search}%")
                  ->orWhere('country', 'ilike', "%{$search}%");
            });
        }

        $contacts = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['success' => true, 'data' => $contacts]);
    }

    public function createContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'category_id' => 'nullable|uuid|exists:job_categories,id',
            'source_ad_id' => 'nullable|uuid',
            'source_file' => 'nullable|string|max:255',
            'source_type' => 'nullable|string|in:csv,cv_pdf,cv_docx,cv_image,manual',
            'raw_data' => 'nullable|array',
            'skills' => 'nullable|array',
            'experience_years' => 'nullable|integer|min:0',
            'current_position' => 'nullable|string|max:255',
            'linkedin_url' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:new,contacted,interested,rejected,hired',
            'audit' => 'nullable|array',
        ]);

        $contact = DB::transaction(function () use ($validated) {
            // Check for duplicates before creating
            $duplicate = $this->findDuplicate($validated);
            if ($duplicate) {
                $validated['is_duplicate'] = true;
                $validated['duplicate_of'] = $duplicate->id;
            }

            return JobContact::create($validated);
        });

        return response()->json(['success' => true, 'data' => $contact->load('category')], 201);
    }

    public function updateContact(Request $request, string $id): JsonResponse
    {
        $contact = JobContact::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'category_id' => 'nullable|uuid|exists:job_categories,id',
            'source_ad_id' => 'nullable|uuid',
            'skills' => 'nullable|array',
            'experience_years' => 'nullable|integer|min:0',
            'current_position' => 'nullable|string|max:255',
            'linkedin_url' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:new,contacted,interested,rejected,hired',
            'is_duplicate' => 'nullable|boolean',
            'duplicate_of' => 'nullable|uuid',
            'audit' => 'nullable|array',
        ]);

        $contact->update($validated);

        return response()->json(['success' => true, 'data' => $contact->fresh()->load('category')]);
    }

    public function destroyContact(string $id): JsonResponse
    {
        $contact = JobContact::findOrFail($id);
        $contact->delete();

        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'uuid',
        ]);

        $count = JobContact::whereIn('id', $request->input('ids'))->delete();

        return response()->json(['success' => true, 'deleted' => $count]);
    }

    public function bulkUpdateCategory(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'uuid',
            'category_id' => 'nullable|uuid|exists:job_categories,id',
        ]);

        $count = JobContact::whereIn('id', $request->input('ids'))
            ->update(['category_id' => $request->input('category_id')]);

        return response()->json(['success' => true, 'updated' => $count]);
    }

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'uuid',
            'status' => 'required|string|in:new,contacted,interested,rejected,hired',
        ]);

        $count = JobContact::whereIn('id', $request->input('ids'))
            ->update(['status' => $request->input('status')]);

        return response()->json(['success' => true, 'updated' => $count]);
    }

    /* =====================================================================
     * CONTACTS — IMPORT CSV
     * =================================================================== */

    public function importCSV(Request $request): JsonResponse
    {
        $request->validate([
            'contacts' => 'required|array|min:1|max:5000',
            'contacts.*.first_name' => 'nullable|string|max:255',
            'contacts.*.last_name' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.city' => 'nullable|string|max:100',
            'contacts.*.country' => 'nullable|string|max:100',
            'contacts.*.current_position' => 'nullable|string|max:255',
            'contacts.*.skills' => 'nullable|array|max:50',
            'contacts.*.skills.*' => 'string|max:100',
            'category_id' => 'nullable|uuid|exists:job_categories,id',
            'source_ad_id' => 'nullable|uuid',
            'operator' => 'nullable|string|max:100',
        ]);

        $categoryId = $request->input('category_id');
        $sourceAdId = $request->input('source_ad_id');
        $operator = $request->input('operator', 'system');
        $created = 0;
        $duplicates = 0;

        foreach ($request->input('contacts') as $contactData) {
            $contactData['category_id'] = $categoryId;
            $contactData['source_ad_id'] = $sourceAdId;
            $contactData['source_type'] = 'csv';
            $contactData['audit'] = [[
                'date' => now()->toISOString(),
                'who' => $operator,
                'action' => 'Importé depuis CSV',
            ]];

            // Check for duplicates
            $duplicate = $this->findDuplicate($contactData);
            if ($duplicate) {
                $contactData['is_duplicate'] = true;
                $contactData['duplicate_of'] = $duplicate->id;
                $duplicates++;
            }

            if (!empty($contactData['first_name']) || !empty($contactData['last_name']) || !empty($contactData['email']) || !empty($contactData['phone'])) {
                JobContact::create($contactData);
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'duplicates' => $duplicates,
        ]);
    }

    /* =====================================================================
     * CONTACTS — IMPORT ZIP (with AI analysis)
     * =================================================================== */

    public function importZip(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:zip|max:102400', // 100MB max
            'category_id' => 'nullable|uuid|exists:job_categories,id',
            'source_ad_id' => 'nullable|uuid',
            'operator' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $categoryId = $request->input('category_id');
        $sourceAdId = $request->input('source_ad_id');
        $operator = $request->input('operator', 'system');

        $tempPath = $file->store('temp', 'local');
        $fullPath = storage_path('app/private/' . $tempPath);

        $zip = new ZipArchive();
        if ($zip->open($fullPath) !== true) {
            @unlink($fullPath);
            return response()->json(['success' => false, 'error' => 'Impossible d\'ouvrir le fichier ZIP'], 422);
        }

        $cvService = new CvAnalysisService();
        $results = ['created' => 0, 'duplicates' => 0, 'errors' => 0, 'files_processed' => 0, 'details' => []];

        // Safety: limit number of files to prevent ZIP bombs
        $maxFiles = 500;
        $fileCount = min($zip->numFiles, $maxFiles);

        for ($i = 0; $i < $fileCount; $i++) {
            $stat = $zip->statIndex($i);
            $filename = $stat['name'];
            $baseName = basename($filename);

            // Skip directories, hidden files, path traversal attempts
            if (str_ends_with($filename, '/')
                || str_starts_with($baseName, '.')
                || str_starts_with($baseName, '__')
                || str_contains($filename, '..')
                || $baseName === '') {
                continue;
            }

            $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
            $content = $zip->getFromIndex($i);

            // Skip empty files or files > 25MB (ZIP bomb protection)
            if (!$content || strlen($content) > 25 * 1024 * 1024) {
                continue;
            }

            $contactData = null;

            try {
                if ($ext === 'csv') {
                    // Parse CSV file inside ZIP
                    $csvContacts = $this->parseCSVContent($content, $categoryId, $sourceAdId, $operator, $filename);
                    foreach ($csvContacts as $csvContact) {
                        $duplicate = $this->findDuplicate($csvContact);
                        if ($duplicate) {
                            $csvContact['is_duplicate'] = true;
                            $csvContact['duplicate_of'] = $duplicate->id;
                            $results['duplicates']++;
                        }
                        JobContact::create($csvContact);
                        $results['created']++;
                    }
                    $results['files_processed']++;
                    $results['details'][] = ['file' => $filename, 'status' => 'ok', 'contacts' => count($csvContacts)];
                    continue;
                }

                if (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'rtf'])) {
                    // For PDF/DOCX, extract text and analyze
                    $textContent = $this->extractTextFromFile($content, $ext, $filename);
                    if ($textContent) {
                        $contactData = $cvService->analyzeText($textContent, $filename);
                    }
                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    // For images, send to AI vision
                    $base64 = base64_encode($content);
                    $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
                    $contactData = $cvService->analyzeFile($base64, $filename, $mimeTypes[$ext] ?? 'image/jpeg');
                }

                if ($contactData) {
                    $contactData['category_id'] = $categoryId;
                    $contactData['source_ad_id'] = $sourceAdId;
                    $contactData['source_file'] = basename($filename);
                    $contactData['source_type'] = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? 'cv_image' : ($ext === 'pdf' ? 'cv_pdf' : 'cv_docx');
                    $contactData['audit'] = [[
                        'date' => now()->toISOString(),
                        'who' => $operator,
                        'action' => "Extrait par IA depuis {$filename}",
                    ]];

                    $duplicate = $this->findDuplicate($contactData);
                    if ($duplicate) {
                        $contactData['is_duplicate'] = true;
                        $contactData['duplicate_of'] = $duplicate->id;
                        $results['duplicates']++;
                    }

                    JobContact::create($contactData);
                    $results['created']++;
                    $results['details'][] = ['file' => $filename, 'status' => 'ok'];
                } else {
                    $results['errors']++;
                    $results['details'][] = ['file' => $filename, 'status' => 'error', 'reason' => 'Analyse IA échouée'];
                }
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = ['file' => $filename, 'status' => 'error', 'reason' => $e->getMessage()];
                Log::warning('JobContactController: ZIP file processing error', [
                    'file' => $filename,
                    'error' => $e->getMessage(),
                ]);
            }

            $results['files_processed']++;
        }

        $zip->close();
        @unlink($fullPath);

        return response()->json(array_merge(['success' => true], $results));
    }

    /* =====================================================================
     * CONTACTS — UPLOAD SINGLE FILE FOR AI ANALYSIS
     * =================================================================== */

    public function analyzeFile(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,docx,doc,csv,txt,rtf,jpg,jpeg,png,webp|max:20480',
            'category_id' => 'nullable|uuid|exists:job_categories,id',
            'source_ad_id' => 'nullable|uuid',
            'operator' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());
        $cvService = new CvAnalysisService();

        $contactData = null;

        if (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'rtf'])) {
            $textContent = $this->extractTextFromFile($file->get(), $ext, $filename);
            if ($textContent) {
                $contactData = $cvService->analyzeText($textContent, $filename);
            }
        } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $base64 = base64_encode($file->get());
            $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
            $contactData = $cvService->analyzeFile($base64, $filename, $mimeTypes[$ext] ?? 'image/jpeg');
        } elseif ($ext === 'csv') {
            $contacts = $this->parseCSVContent(
                $file->get(),
                $request->input('category_id'),
                $request->input('source_ad_id'),
                $request->input('operator', 'system'),
                $filename
            );
            return response()->json(['success' => true, 'contacts' => $contacts, 'type' => 'csv']);
        }

        if (!$contactData) {
            return response()->json(['success' => false, 'error' => 'Impossible d\'analyser ce fichier'], 422);
        }

        $contactData['category_id'] = $request->input('category_id');
        $contactData['source_ad_id'] = $request->input('source_ad_id');
        $contactData['source_file'] = $filename;
        $contactData['source_type'] = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? 'cv_image' : ($ext === 'pdf' ? 'cv_pdf' : 'cv_docx');
        $contactData['audit'] = [[
            'date' => now()->toISOString(),
            'who' => $request->input('operator', 'system'),
            'action' => "Extrait par IA depuis {$filename}",
        ]];

        // Check duplicate
        $duplicate = $this->findDuplicate($contactData);
        if ($duplicate) {
            $contactData['is_duplicate'] = true;
            $contactData['duplicate_of'] = $duplicate->id;
        }

        $contact = JobContact::create($contactData);

        return response()->json([
            'success' => true,
            'data' => $contact->load('category'),
            'type' => 'single',
            'is_duplicate' => $contactData['is_duplicate'] ?? false,
        ]);
    }

    /* =====================================================================
     * CONTACTS — DEDUPLICATION
     * =================================================================== */

    public function getDuplicates(): JsonResponse
    {
        $duplicates = JobContact::where('is_duplicate', true)
            ->with(['category', 'duplicateOf'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $duplicates]);
    }

    public function mergeDuplicates(Request $request): JsonResponse
    {
        $request->validate([
            'keep_id' => 'required|uuid|exists:job_contacts,id',
            'merge_id' => 'required|uuid|exists:job_contacts,id',
        ]);

        $keep = JobContact::findOrFail($request->input('keep_id'));
        $merge = JobContact::findOrFail($request->input('merge_id'));

        // Merge non-null fields from merge into keep
        $fields = ['first_name', 'last_name', 'email', 'phone', 'city', 'country',
                    'current_position', 'experience_years', 'linkedin_url'];

        foreach ($fields as $field) {
            if (empty($keep->{$field}) && !empty($merge->{$field})) {
                $keep->{$field} = $merge->{$field};
            }
        }

        // Merge skills
        $keepSkills = $keep->skills ?? [];
        $mergeSkills = $merge->skills ?? [];
        $keep->skills = array_values(array_unique(array_merge($keepSkills, $mergeSkills)));

        // Add audit entry
        $audit = $keep->audit ?? [];
        $audit[] = [
            'date' => now()->toISOString(),
            'who' => 'system',
            'action' => 'Fusionné avec contact #' . Str::limit($merge->id, 8, ''),
        ];
        $keep->audit = $audit;
        $keep->save();

        // Delete the merged contact
        $merge->delete();

        return response()->json(['success' => true, 'data' => $keep->fresh()->load('category')]);
    }

    /* =====================================================================
     * STATS
     * =================================================================== */

    public function contactStats(): JsonResponse
    {
        $totalContacts = JobContact::count();
        $byStatus = JobContact::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
        $byCategory = JobCategory::withCount('contacts')
            ->orderBy('contacts_count', 'desc')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color, 'count' => $c->contacts_count]);
        $duplicateCount = JobContact::where('is_duplicate', true)->count();
        $totalCategories = JobCategory::count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_contacts' => $totalContacts,
                'by_status' => $byStatus,
                'by_category' => $byCategory,
                'duplicates' => $duplicateCount,
                'total_categories' => $totalCategories,
            ],
        ]);
    }

    /* =====================================================================
     * SOS-EXPAT CROSS-CHECK — Query Firestore to find registered users
     * =================================================================== */

    public function checkSosStatus(Request $request): JsonResponse
    {
        $request->validate([
            'contact_ids' => 'required|array|min:1|max:200',
            'contact_ids.*' => 'uuid',
        ]);

        $contacts = JobContact::whereIn('id', $request->input('contact_ids'))
            ->where(function ($q) {
                $q->whereNotNull('email')->orWhereNotNull('phone');
            })
            ->get(['id', 'email', 'phone']);

        if ($contacts->isEmpty()) {
            return response()->json(['success' => true, 'results' => [], 'checked' => 0]);
        }

        $sosService = new \App\Services\SosUserCheckService(
            new \App\Services\FirestoreService()
        );

        $batch = $contacts->map(fn ($c) => [
            'id' => $c->id,
            'email' => $c->email,
            'phone' => $c->phone,
        ])->toArray();

        $results = $sosService->checkBatch($batch);

        // Save results to database
        $found = 0;
        foreach ($results as $contactId => $status) {
            JobContact::where('id', $contactId)->update([
                'sos_registered' => json_encode($status),
            ]);
            if ($status['found'] ?? false) {
                $found++;
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'checked' => count($results),
            'found' => $found,
        ]);
    }

    public function checkAllSosStatus(): JsonResponse
    {
        // Check all contacts that haven't been checked yet (or checked > 24h ago)
        $contacts = JobContact::where(function ($q) {
            $q->whereNull('sos_registered')
              ->orWhereRaw("(sos_registered->>'checkedAt')::timestamp < now() - interval '24 hours'");
        })
            ->where(function ($q) {
                $q->whereNotNull('email')->orWhereNotNull('phone');
            })
            ->limit(200)
            ->get(['id', 'email', 'phone']);

        if ($contacts->isEmpty()) {
            return response()->json(['success' => true, 'checked' => 0, 'found' => 0, 'message' => 'Tous les contacts ont déjà été vérifiés']);
        }

        $sosService = new \App\Services\SosUserCheckService(
            new \App\Services\FirestoreService()
        );

        $batch = $contacts->map(fn ($c) => [
            'id' => $c->id,
            'email' => $c->email,
            'phone' => $c->phone,
        ])->toArray();

        $results = $sosService->checkBatch($batch);

        $found = 0;
        foreach ($results as $contactId => $status) {
            JobContact::where('id', $contactId)->update([
                'sos_registered' => json_encode($status),
            ]);
            if ($status['found'] ?? false) {
                $found++;
            }
        }

        return response()->json([
            'success' => true,
            'checked' => count($results),
            'found' => $found,
            'remaining' => JobContact::whereNull('sos_registered')
                ->where(function ($q) {
                    $q->whereNotNull('email')->orWhereNotNull('phone');
                })->count(),
        ]);
    }

    /* =====================================================================
     * PRIVATE HELPERS
     * =================================================================== */

    private function findDuplicate(array $data): ?JobContact
    {
        // 1. Match by exact email
        if (!empty($data['email'])) {
            $byEmail = JobContact::where('email', $data['email'])->first();
            if ($byEmail) {
                return $byEmail;
            }
        }

        // 2. Match by phone
        if (!empty($data['phone'])) {
            $normalizedPhone = preg_replace('/[^0-9+]/', '', $data['phone']);
            if (strlen($normalizedPhone) >= 8) {
                $byPhone = JobContact::whereRaw(
                    "regexp_replace(phone, '[^0-9+]', '', 'g') = ?",
                    [$normalizedPhone]
                )->first();
                if ($byPhone) {
                    return $byPhone;
                }
            }
        }

        // 3. Match by exact first_name + last_name (case-insensitive)
        if (!empty($data['first_name']) && !empty($data['last_name'])) {
            $byName = JobContact::where('first_name', 'ilike', $data['first_name'])
                ->where('last_name', 'ilike', $data['last_name'])
                ->first();
            if ($byName) {
                return $byName;
            }
        }

        return null;
    }

    private function parseCSVContent(string $content, ?string $categoryId, ?string $sourceAdId, string $operator, string $filename): array
    {
        $lines = self::parseCsvString($content);
        if (count($lines) < 2) {
            return [];
        }

        $headers = $lines[0];
        $cvService = new CvAnalysisService();
        $contacts = [];

        for ($i = 1; $i < count($lines); $i++) {
            $row = $lines[$i];
            if (empty(array_filter($row))) {
                continue;
            }

            $contactData = $cvService->analyzeCSVRow($row, $headers);
            $contactData['category_id'] = $categoryId;
            $contactData['source_ad_id'] = $sourceAdId;
            $contactData['source_file'] = basename($filename);
            $contactData['source_type'] = 'csv';
            $contactData['audit'] = [[
                'date' => now()->toISOString(),
                'who' => $operator,
                'action' => "Importé depuis CSV ({$filename})",
            ]];

            if (!empty($contactData['first_name']) || !empty($contactData['last_name']) || !empty($contactData['email']) || !empty($contactData['phone'])) {
                $contacts[] = $contactData;
            }
        }

        return $contacts;
    }

    private function extractTextFromFile(string $content, string $ext, string $filename): ?string
    {
        if ($ext === 'txt' || $ext === 'rtf') {
            return $content;
        }

        if ($ext === 'pdf') {
            return $this->extractTextFromPDF($content);
        }

        if ($ext === 'docx') {
            return $this->extractTextFromDocx($content);
        }

        return null;
    }

    private function extractTextFromPDF(string $content): ?string
    {
        // Basic PDF text extraction (works for text-based PDFs)
        // For scanned PDFs, the AI vision endpoint should be used instead
        $text = '';

        // Try to extract text between stream/endstream with FlateDecode
        if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded) {
                    // Extract text operators (Tj, TJ, ')
                    if (preg_match_all('/\((.*?)\)\s*Tj/s', $decoded, $textMatches)) {
                        $text .= implode(' ', $textMatches[1]) . "\n";
                    }
                    if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $tjMatches)) {
                        foreach ($tjMatches[1] as $tj) {
                            if (preg_match_all('/\((.*?)\)/', $tj, $parts)) {
                                $text .= implode('', $parts[1]) . ' ';
                            }
                        }
                        $text .= "\n";
                    }
                }
            }
        }

        // Fallback: try to find readable text directly
        if (empty(trim($text))) {
            // Simple regex to grab readable text fragments
            if (preg_match_all('/\(([\x20-\x7e]{3,})\)/', $content, $matches)) {
                $text = implode(' ', $matches[1]);
            }
        }

        return !empty(trim($text)) ? trim($text) : null;
    }

    private function extractTextFromDocx(string $content): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'docx_');
        file_put_contents($tempFile, $content);

        $zip = new ZipArchive();
        if ($zip->open($tempFile) !== true) {
            @unlink($tempFile);
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tempFile);

        if (!$xml) {
            return null;
        }

        // Strip XML tags and get text content
        $text = strip_tags(str_replace(['<w:p>', '<w:br/>'], ["\n", "\n"], $xml));
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n", $text);

        return !empty(trim($text)) ? trim($text) : null;
    }

    /**
     * Parse CSV content string into array of arrays.
     */
    private static function parseCsvString(string $content): array
    {
        $lines = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $lines[] = $row;
        }

        fclose($handle);
        return $lines;
    }
}
