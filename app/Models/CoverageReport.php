<?php

namespace App\Models;

use Database\Factories\CoverageReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoverageReport extends Model
{
    /** @use HasFactory<CoverageReportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'repository_id',
        'branch',
        'commit_sha',
        'coverage_percentage',
        'status',
        'error_message',
        'clover_file_path',
        'archived',
        'archived_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coverage_percentage' => 'decimal:2',
            'archived' => 'boolean',
            'archived_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(CoverageFile::class);
    }

    /**
     * @param  Builder<CoverageReport>  $query
     * @return Builder<CoverageReport>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('archived', false);
    }
}
