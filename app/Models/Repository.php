<?php

namespace App\Models;

use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
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

    public function pullRequestComments(): HasMany
    {
        return $this->hasMany(PullRequestComment::class);
    }

    public function latestCoverageReport(): HasOne
    {
        return $this->hasOne(CoverageReport::class)
            ->ofMany(
                ['created_at' => 'max'],
                fn (Builder $query) => $query
                    ->join('repositories', 'repositories.id', '=', 'coverage_reports.repository_id')
                    ->where('coverage_reports.archived', false)
                    ->whereColumn('coverage_reports.branch', 'repositories.default_branch'),
            );
    }

    public function fileCache(): HasMany
    {
        return $this->hasMany(RepositoryFileCache::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @param  Builder<Repository>  $query
     * @return Builder<Repository>
     */
    public function scopeForTeams(Builder $query, Collection|array $teamIds): Builder
    {
        return $query->whereIn('team_id', $teamIds);
    }

    /**
     * @param  Builder<Repository>  $query
     * @return Builder<Repository>
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }
}
