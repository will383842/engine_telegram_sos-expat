<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobContact extends Model
{
    use HasUuids;

    protected $table = 'job_contacts';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'city',
        'country',
        'category_id',
        'source_ad_id',
        'source_file',
        'source_type',
        'raw_data',
        'skills',
        'experience_years',
        'current_position',
        'linkedin_url',
        'notes',
        'status',
        'is_duplicate',
        'duplicate_of',
        'audit',
        'sos_registered',
    ];

    protected $attributes = [
        'status' => 'new',
        'source_type' => 'manual',
        'raw_data' => '{}',
        'skills' => '[]',
        'audit' => '[]',
        'is_duplicate' => false,
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'skills' => 'array',
            'audit' => 'array',
            'experience_years' => 'integer',
            'is_duplicate' => 'boolean',
            'sos_registered' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'category_id');
    }

    public function sourceAd(): BelongsTo
    {
        return $this->belongsTo(JobAd::class, 'source_ad_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
}
