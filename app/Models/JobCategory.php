<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobCategory extends Model
{
    use HasUuids;

    protected $table = 'job_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(JobContact::class, 'category_id');
    }

    public function ads(): \Illuminate\Database\Eloquent\Collection
    {
        return JobAd::where('category', $this->name)->get();
    }
}
