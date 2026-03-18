<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CvAnalysisService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('engine.anthropic_api_key', '');
        $this->model = config('engine.cv_analysis_model', 'claude-sonnet-4-20250514');
    }

    /**
     * Analyze a CSV row of candidate data and extract structured contact info.
     */
    public function analyzeCSVRow(array $row, array $headers): ?array
    {
        // For CSV, we do direct mapping without AI (faster)
        return $this->mapCSVToContact($row, $headers);
    }

    /**
     * Analyze file content (PDF text, DOCX text, or image base64) with AI.
     */
    public function analyzeFile(string $content, string $filename, string $mimeType): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('CvAnalysisService: No ANTHROPIC_API_KEY configured');
            return null;
        }

        try {
            $messages = $this->buildAnalysisMessages($content, $filename, $mimeType);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2024-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 2000,
                'messages' => $messages,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $text = $result['content'][0]['text'] ?? '';
                return $this->parseAIResponse($text);
            }

            Log::warning('CvAnalysisService: API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('CvAnalysisService: Exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Analyze text content extracted from a file (e.g., PDF text).
     */
    public function analyzeText(string $text, string $filename): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2024-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 2000,
                'messages' => [[
                    'role' => 'user',
                    'content' => $this->getTextPrompt($text, $filename),
                ]],
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $responseText = $result['content'][0]['text'] ?? '';
                return $this->parseAIResponse($responseText);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('CvAnalysisService: Exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildAnalysisMessages(string $content, string $filename, string $mimeType): array
    {
        // For images, use vision
        if (str_starts_with($mimeType, 'image/')) {
            return [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mimeType,
                            'data' => $content, // already base64
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => $this->getImagePrompt($filename),
                    ],
                ],
            ]];
        }

        // For text-based content (PDF text, DOCX text)
        return [[
            'role' => 'user',
            'content' => $this->getTextPrompt($content, $filename),
        ]];
    }

    private function getTextPrompt(string $content, string $filename): string
    {
        return <<<PROMPT
Analyse ce CV/document de candidature et extrais les informations structurées.
Fichier: {$filename}

Contenu:
---
{$content}
---

Réponds UNIQUEMENT avec un JSON valide (pas de texte avant/après) avec cette structure:
{
  "first_name": "string ou null",
  "last_name": "string ou null",
  "email": "string ou null",
  "phone": "string ou null",
  "city": "string ou null",
  "country": "string ou null",
  "current_position": "string ou null (poste actuel ou dernier poste)",
  "experience_years": number ou null,
  "skills": ["skill1", "skill2", ...],
  "linkedin_url": "string ou null",
  "education": "string ou null (dernier diplôme)",
  "languages": ["langue1", "langue2", ...],
  "summary": "string (résumé en 1-2 phrases du profil)"
}

Si une information n'est pas trouvée, mets null. Extrais TOUTES les informations disponibles.
PROMPT;
    }

    private function getImagePrompt(string $filename): string
    {
        return <<<PROMPT
Cette image est un CV ou un document de candidature.
Fichier: {$filename}

Analyse-le et extrais les informations structurées.
Réponds UNIQUEMENT avec un JSON valide (pas de texte avant/après) avec cette structure:
{
  "first_name": "string ou null",
  "last_name": "string ou null",
  "email": "string ou null",
  "phone": "string ou null",
  "city": "string ou null",
  "country": "string ou null",
  "current_position": "string ou null (poste actuel ou dernier poste)",
  "experience_years": number ou null,
  "skills": ["skill1", "skill2", ...],
  "linkedin_url": "string ou null",
  "education": "string ou null (dernier diplôme)",
  "languages": ["langue1", "langue2", ...],
  "summary": "string (résumé en 1-2 phrases du profil)"
}

Si une information n'est pas trouvée, mets null.
PROMPT;
    }

    private function parseAIResponse(string $text): ?array
    {
        // Extract JSON from response (handle potential markdown code blocks)
        $text = trim($text);
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);
        }

        $data = json_decode($text, true);
        if (!is_array($data)) {
            Log::warning('CvAnalysisService: Failed to parse AI response', ['text' => $text]);
            return null;
        }

        return [
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'current_position' => $data['current_position'] ?? null,
            'experience_years' => isset($data['experience_years']) ? (int) $data['experience_years'] : null,
            'skills' => $data['skills'] ?? [],
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'raw_data' => $data, // keep full AI response
        ];
    }

    /**
     * Map CSV columns to contact fields without AI.
     */
    private function mapCSVToContact(array $row, array $headers): array
    {
        $mappings = [
            'prenom' => 'first_name', 'first_name' => 'first_name', 'firstname' => 'first_name',
            'first name' => 'first_name', 'prénom' => 'first_name',
            'nom' => 'last_name', 'last_name' => 'last_name', 'lastname' => 'last_name',
            'last name' => 'last_name', 'nom de famille' => 'last_name', 'name' => 'last_name',
            'email' => 'email', 'e-mail' => 'email', 'mail' => 'email', 'courriel' => 'email',
            'adresse email' => 'email', 'email address' => 'email',
            'telephone' => 'phone', 'téléphone' => 'phone', 'phone' => 'phone', 'tel' => 'phone',
            'mobile' => 'phone', 'phone number' => 'phone', 'numéro' => 'phone',
            'ville' => 'city', 'city' => 'city', 'town' => 'city',
            'pays' => 'country', 'country' => 'country',
            'poste' => 'current_position', 'position' => 'current_position',
            'poste actuel' => 'current_position', 'current position' => 'current_position',
            'titre' => 'current_position', 'job title' => 'current_position',
            'experience' => 'experience_years', 'expérience' => 'experience_years',
            'années expérience' => 'experience_years', 'years experience' => 'experience_years',
            'competences' => 'skills', 'compétences' => 'skills', 'skills' => 'skills',
            'linkedin' => 'linkedin_url', 'linkedin url' => 'linkedin_url',
            'notes' => 'notes', 'commentaire' => 'notes', 'comment' => 'notes',
            'remarque' => 'notes', 'observations' => 'notes',
        ];

        $contact = [];
        foreach ($headers as $i => $header) {
            $normalized = mb_strtolower(trim(preg_replace('/[^a-zà-ÿ\s]/u', '', $header)));
            $field = $mappings[$normalized] ?? $mappings[mb_strtolower(trim($header))] ?? null;

            if ($field && isset($row[$i])) {
                $val = trim($row[$i]);
                if ($field === 'experience_years') {
                    $contact[$field] = (int) preg_replace('/[^0-9]/', '', $val);
                } elseif ($field === 'skills') {
                    $contact[$field] = array_filter(array_map('trim', preg_split('/[;,|]/', $val)));
                } else {
                    $contact[$field] = $val;
                }
            }
        }

        return $contact;
    }
}
