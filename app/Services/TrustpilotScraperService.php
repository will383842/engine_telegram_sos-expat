<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TrustpilotSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrustpilotScraperService
{
    /**
     * Scrape the Trustpilot profile page and return parsed data.
     *
     * @return array{
     *     trust_score: float|null,
     *     total_reviews: int,
     *     stars_5: int,
     *     stars_4: int,
     *     stars_3: int,
     *     stars_2: int,
     *     stars_1: int,
     *     reviews: array<int, array{author: string, rating: int, title: string, text: string, date: string}>,
     *     raw: array|null,
     * }
     *
     * @throws \RuntimeException
     */
    public function scrape(): array
    {
        $url = config('trustpilot.profile_url');
        $timeout = config('trustpilot.timeout', 30);
        $userAgent = config('trustpilot.user_agent');

        Log::info('TrustpilotScraper: Fetching profile', ['url' => $url]);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'User-Agent' => $userAgent,
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("Trustpilot returned HTTP {$response->status()}");
        }

        $html = $response->body();

        return $this->parseHtml($html);
    }

    /**
     * Parse the HTML page and extract data from __NEXT_DATA__ JSON.
     */
    protected function parseHtml(string $html): array
    {
        // Extract __NEXT_DATA__ JSON from the page
        $nextData = $this->extractNextData($html);

        if ($nextData) {
            return $this->parseFromNextData($nextData);
        }

        // Fallback: parse from HTML meta/structured data
        return $this->parseFromHtmlFallback($html);
    }

    /**
     * Extract the __NEXT_DATA__ JSON object from the HTML.
     */
    protected function extractNextData(string $html): ?array
    {
        // Match <script id="__NEXT_DATA__" type="application/json">...</script>
        if (!preg_match('/<script\s+id="__NEXT_DATA__"\s+type="application\/json">\s*({.+?})\s*<\/script>/s', $html, $matches)) {
            Log::warning('TrustpilotScraper: __NEXT_DATA__ not found, using fallback');
            return null;
        }

        $json = json_decode($matches[1], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('TrustpilotScraper: Failed to parse __NEXT_DATA__ JSON', [
                'error' => json_last_error_msg(),
            ]);
            return null;
        }

        return $json;
    }

    /**
     * Parse data from the __NEXT_DATA__ JSON structure.
     */
    protected function parseFromNextData(array $nextData): array
    {
        $pageProps = $nextData['props']['pageProps'] ?? [];
        $businessUnit = $pageProps['businessUnit'] ?? [];
        $reviews = $pageProps['reviews'] ?? [];
        $filters = $pageProps['filters'] ?? [];

        // Extract trust score
        $trustScore = $businessUnit['trustScore'] ?? null;

        // Extract total review count
        $totalReviews = $businessUnit['numberOfReviews'] ?? 0;

        // Extract star distribution from filters.reviewStatistics.ratings
        $starDistribution = $this->extractStarDistribution($filters, $businessUnit);

        // Extract individual reviews
        $parsedReviews = $this->extractReviews($reviews);

        return [
            'trust_score' => $trustScore,
            'total_reviews' => $totalReviews,
            'stars_5' => $starDistribution[5] ?? 0,
            'stars_4' => $starDistribution[4] ?? 0,
            'stars_3' => $starDistribution[3] ?? 0,
            'stars_2' => $starDistribution[2] ?? 0,
            'stars_1' => $starDistribution[1] ?? 0,
            'reviews' => $parsedReviews,
            'raw' => [
                'businessUnit' => $businessUnit,
                'ratings' => $filters['reviewStatistics']['ratings'] ?? null,
            ],
        ];
    }

    /**
     * Extract star distribution from Trustpilot data.
     *
     * Primary: filters.reviewStatistics.ratings (keys: one, two, three, four, five)
     * Fallback: businessUnit.starsDistribution (array of {stars, count})
     *
     * @return array<int, int>
     */
    protected function extractStarDistribution(array $filters, array $businessUnit): array
    {
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        // Primary: filters.reviewStatistics.ratings {one, two, three, four, five}
        $ratings = $filters['reviewStatistics']['ratings'] ?? [];
        if (!empty($ratings)) {
            $wordMap = ['five' => 5, 'four' => 4, 'three' => 3, 'two' => 2, 'one' => 1];
            foreach ($wordMap as $word => $star) {
                $distribution[$star] = (int) ($ratings[$word] ?? 0);
            }
            return $distribution;
        }

        // Fallback: businessUnit.starsDistribution (array of {stars: int, count: int})
        $starsDist = $businessUnit['starsDistribution'] ?? [];
        if (!empty($starsDist)) {
            foreach ($starsDist as $entry) {
                $stars = (int) ($entry['stars'] ?? 0);
                $count = (int) ($entry['count'] ?? 0);
                if (isset($distribution[$stars])) {
                    $distribution[$stars] = $count;
                }
            }
        }

        return $distribution;
    }

    /**
     * Extract individual reviews from the reviews data.
     *
     * @return array<int, array{author: string, rating: int, title: string, text: string, date: string}>
     */
    protected function extractReviews(array $reviews): array
    {
        $parsed = [];

        foreach ($reviews as $review) {
            $parsed[] = [
                'author' => $review['consumer']['displayName'] ?? 'Anonyme',
                'rating' => (int) ($review['rating'] ?? 0),
                'title' => $review['title'] ?? '',
                'text' => mb_substr($review['text'] ?? '', 0, 300),
                'date' => $review['dates']['publishedDate'] ?? $review['createdAt'] ?? '',
            ];
        }

        return $parsed;
    }

    /**
     * Fallback: parse from HTML when __NEXT_DATA__ is unavailable.
     */
    protected function parseFromHtmlFallback(string $html): array
    {
        $trustScore = null;
        $totalReviews = 0;

        // Try to extract from JSON-LD structured data
        if (preg_match('/<script type="application\/ld\+json">\s*({.+?"@type"\s*:\s*"LocalBusiness".+?})\s*<\/script>/s', $html, $matches)) {
            $jsonLd = json_decode($matches[1], true);
            if ($jsonLd) {
                $trustScore = (float) ($jsonLd['aggregateRating']['ratingValue'] ?? 0);
                $totalReviews = (int) ($jsonLd['aggregateRating']['reviewCount'] ?? 0);
            }
        }

        // Try meta tags
        if (!$trustScore && preg_match('/data-rating="([\d.]+)"/', $html, $m)) {
            $trustScore = (float) $m[1];
        }

        return [
            'trust_score' => $trustScore,
            'total_reviews' => $totalReviews,
            'stars_5' => 0,
            'stars_4' => 0,
            'stars_3' => 0,
            'stars_2' => 0,
            'stars_1' => 0,
            'reviews' => [],
            'raw' => null,
        ];
    }

    /**
     * Create a snapshot from scraped data, comparing with the previous snapshot.
     */
    public function createSnapshot(array $scrapedData): TrustpilotSnapshot
    {
        $previousSnapshot = TrustpilotSnapshot::latest();
        $previousTotal = $previousSnapshot?->total_reviews ?? 0;

        // Filter reviews from the last 7 days
        $newReviews = $this->filterRecentReviews($scrapedData['reviews'], 7);

        $snapshot = TrustpilotSnapshot::create([
            'trust_score' => $scrapedData['trust_score'],
            'total_reviews' => $scrapedData['total_reviews'],
            'new_reviews_count' => max(0, $scrapedData['total_reviews'] - $previousTotal),
            'stars_5' => $scrapedData['stars_5'],
            'stars_4' => $scrapedData['stars_4'],
            'stars_3' => $scrapedData['stars_3'],
            'stars_2' => $scrapedData['stars_2'],
            'stars_1' => $scrapedData['stars_1'],
            'new_reviews' => $newReviews,
            'raw_data' => $scrapedData['raw'],
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
        ]);

        Log::info('TrustpilotScraper: Snapshot created', [
            'trust_score' => $snapshot->trust_score,
            'total_reviews' => $snapshot->total_reviews,
            'new_reviews_count' => $snapshot->new_reviews_count,
        ]);

        return $snapshot;
    }

    /**
     * Filter reviews published within the last N days.
     */
    protected function filterRecentReviews(array $reviews, int $days): array
    {
        $cutoff = now()->subDays($days);

        return collect($reviews)
            ->filter(function (array $review) use ($cutoff) {
                if (empty($review['date'])) {
                    return true; // include if no date (benefit of the doubt)
                }
                try {
                    return Carbon::parse($review['date'])->gte($cutoff);
                } catch (\Throwable) {
                    return true;
                }
            })
            ->values()
            ->toArray();
    }

    /**
     * Format a snapshot into a Telegram message (HTML).
     */
    public function formatTelegramReport(TrustpilotSnapshot $snapshot): string
    {
        $previousSnapshot = TrustpilotSnapshot::orderBy('created_at', 'desc')
            ->where('id', '<', $snapshot->id)
            ->first();

        // Header
        $periodStart = $snapshot->period_start?->format('d/m') ?? '?';
        $periodEnd = $snapshot->period_end?->format('d/m') ?? '?';
        $message = "📊 <b>Trustpilot — Rapport hebdo ({$periodStart} → {$periodEnd})</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━\n\n";

        // Trust score with delta
        $hasScore = $snapshot->trust_score !== null && (float) $snapshot->trust_score > 0;
        $scoreStr = $hasScore ? number_format((float) $snapshot->trust_score, 1) : '—';
        $scoreDelta = '';
        $prevHasScore = $previousSnapshot?->trust_score !== null && (float) ($previousSnapshot?->trust_score ?? 0) > 0;
        if ($prevHasScore && $hasScore) {
            $diff = round((float) $snapshot->trust_score - (float) $previousSnapshot->trust_score, 1);
            if ($diff > 0) {
                $scoreDelta = " (<b>+{$diff}</b> ↑)";
            } elseif ($diff < 0) {
                $scoreDelta = " (<b>{$diff}</b> ↓)";
            }
        }
        $message .= "⭐ Note globale : <b>{$scoreStr}/5</b>{$scoreDelta}\n";

        // Total reviews with delta
        $newCount = $snapshot->new_reviews_count;
        $newStr = $newCount > 0 ? " (+{$newCount} cette semaine)" : '';
        $message .= "📝 Total avis : <b>{$snapshot->total_reviews}</b>{$newStr}\n\n";

        // Star distribution
        $message .= "📈 <b>Distribution :</b>\n";
        $distribution = $snapshot->getDistribution();
        $starEmojis = ['⭐⭐⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐', '⭐⭐', '⭐'];

        foreach ($distribution as $i => $entry) {
            $bar = $this->progressBar($entry['percent']);
            $message .= "  {$starEmojis[$i]}  {$entry['count']} ({$entry['percent']}%) {$bar}\n";
        }

        // New reviews this week
        $newReviews = $snapshot->new_reviews ?? [];
        if (!empty($newReviews)) {
            $message .= "\n🆕 <b>Avis cette semaine :</b>\n";

            $maxReviews = 5;
            $maxMessageLength = 3800; // Reserve space for footer (Telegram limit: 4096)

            $shown = 0;
            foreach (array_slice($newReviews, 0, $maxReviews) as $review) {
                $reviewBlock = '';
                $stars = str_repeat('⭐', min($review['rating'] ?? 0, 5));
                $author = htmlspecialchars($review['author'] ?? 'Anonyme', ENT_QUOTES);
                $title = htmlspecialchars(mb_substr($review['title'] ?? '', 0, 80), ENT_QUOTES);
                $text = htmlspecialchars(mb_substr($review['text'] ?? '', 0, 100), ENT_QUOTES);

                $reviewBlock .= "\n{$stars} <b>{$author}</b>\n";
                if ($title) {
                    $reviewBlock .= "📌 {$title}\n";
                }
                if ($text) {
                    $reviewBlock .= "💬 <i>\"{$text}\"</i>\n";
                }

                // Check message length before appending
                if (mb_strlen($message . $reviewBlock) > $maxMessageLength) {
                    break;
                }

                $message .= $reviewBlock;
                $shown++;
            }

            $remaining = count($newReviews) - $shown;
            if ($remaining > 0) {
                $message .= "\n... et {$remaining} autre(s)\n";
            }
        }

        // Link
        $profileUrl = config('trustpilot.profile_url');
        $message .= "\n🔗 <a href=\"{$profileUrl}\">Voir sur Trustpilot</a>";

        return $message;
    }

    /**
     * Generate a simple text progress bar.
     */
    protected function progressBar(float $percent): string
    {
        $filled = (int) round($percent / 10);
        $empty = 10 - $filled;

        return str_repeat('▓', $filled) . str_repeat('░', $empty);
    }
}
