<?php

namespace App\Models;

use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner',
        'name',
        'github_url',
        'default_branch',
        'webhook_secret',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'webhook_secret',
    ];

    public function coverageReports(): HasMany
    {
        return $this->hasMany(CoverageReport::class);
    }

    public function latestCoverageReport(): HasOne
    {
        return $this->hasOne(CoverageReport::class)
            ->where('archived', false)
            ->latest();
    }

    public function fileCache(): HasMany
    {
        return $this->hasMany(RepositoryFileCache::class);
    }
}
