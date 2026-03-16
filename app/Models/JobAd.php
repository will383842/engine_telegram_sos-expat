<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobAd extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'job_ads';

    protected $attributes = [
        'status' => 'draft',
        'responses' => 0,
        'response_history' => '[]',
        'tags' => '[]',
        'audit' => '[]',
    ];

    protected $fillable = [
        'title',
        'site',
        'site_url',
        'ad_url',
        'country',
        'lang',
        'status',
        'expiry',
        'category',
        'login',
        'password',
        'responses',
        'response_history',
        'pub_date',
        'notes',
        'poster',
        'tags',
        'audit',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'response_history' => 'array',
            'tags' => 'array',
            'audit' => 'array',
            'responses' => 'integer',
            'expiry' => 'date',
            'pub_date' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeTrashed($query)
    {
        return $query->onlyTrashed();
    }
}
