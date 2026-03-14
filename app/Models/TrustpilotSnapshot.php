<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustpilotSnapshot extends Model
{
    protected $fillable = [
        'trust_score',
        'total_reviews',
        'new_reviews_count',
        'stars_5',
        'stars_4',
        'stars_3',
        'stars_2',
        'stars_1',
        'new_reviews',
        'raw_data',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'trust_score' => 'decimal:1',
        'total_reviews' => 'integer',
        'new_reviews_count' => 'integer',
        'stars_5' => 'integer',
        'stars_4' => 'integer',
        'stars_3' => 'integer',
        'stars_2' => 'integer',
        'stars_1' => 'integer',
        'new_reviews' => 'array',
        'raw_data' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    /**
     * Get the most recent snapshot.
     */
    public static function latest(): ?self
    {
        return self::orderBy('created_at', 'desc')->first();
    }

    /**
     * Get star distribution as a formatted array.
     *
     * @return array<int, array{stars: int, count: int, percent: float}>
     */
    public function getDistribution(): array
    {
        $total = $this->total_reviews ?: 1; // avoid division by zero

        return collect([5, 4, 3, 2, 1])
            ->map(fn (int $star) => [
                'stars' => $star,
                'count' => $this->{"stars_{$star}"},
                'percent' => round(($this->{"stars_{$star}"} / $total) * 100, 1),
            ])
            ->toArray();
    }
}
