<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class JobSite extends Model
{
    use HasUuids;

    protected $table = 'job_sites';

    protected $fillable = [
        'name',
        'url',
        'country',
        'lang',
        'category',
        'preapproval',
        'validity',
        'cost',
        'login',
        'password',
        'notes',
        'is_paid',
        'paid_detected_at',
        'audit',
    ];

    protected function casts(): array
    {
        return [
            'audit' => 'array',
            'validity' => 'integer',
            'cost' => 'decimal:2',
            'is_paid' => 'boolean',
            'paid_detected_at' => 'datetime',
        ];
    }
}
