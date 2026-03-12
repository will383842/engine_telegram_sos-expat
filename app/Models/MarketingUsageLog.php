<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingUsageLog extends Model
{
    public $timestamps = false;

    protected $table = 'marketing_usage_logs';

    protected $fillable = [
        'resource_id',
        'user_uid',
        'user_role',
        'action',
        'language',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(MarketingResource::class, 'resource_id');
    }
}
