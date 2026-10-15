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
        'pr_number',
        'coverage_percentage',
        'status',
        'error_message',
        'clover_file_path',
        'archived',
        'archived_at',
        'completed_at',
        'diff_file_path',
        'diff_index_path',
        'diff_sha256',
        'diff_base_sha',
        'diff_status',
        'diff_error_code',
        'diff_metadata',
        'patch_coverage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pr_number' => 'integer',
            'coverage_percentage' => 'decimal:2',
            'archived' => 'boolean',
            'archived_at' => 'datetime',
            'completed_at' => 'datetime',
            'diff_metadata' => 'array',
            'patch_coverage' => 'array',
        ];
    }

    public function hasPendingDiffWork(): bool
    {
        return $this->updated_at->greaterThan(now()->subMinutes((int) config('coverage.diff.processing_grace_minutes')))
            && (in_array($this->diff_status, ['queued', 'processing'], true)
                || in_array($this->patch_coverage['status'] ?? null, ['awaiting_coverage', 'pending'], true));
    }

    public function diffErrorMessage(): ?string
    {
        return match ($this->diff_error_code) {
            'invalid_diff' => 'The diff is malformed or incomplete. Generate a complete Git unified diff and upload a new report.',
            'invalid_gzip' => 'The compressed diff is damaged or incomplete.',
            'diff_limit_exceeded' => 'The diff exceeds the configured processing limits.',
            'count_mismatch' => 'The diff does not match the declared file and line counts.',
            'checksum_mismatch' => 'The stored diff does not match its upload checksum.',
            'storage_failed' => 'The diff could not be processed. Please try again later.',
            default => null,
        };
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
