<?php

namespace App\Models;

use Database\Factories\CoverageFileFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverageFile extends Model
{
    /** @use HasFactory<CoverageFileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'coverage_report_id',
        'file_path',
        'total_lines',
        'covered_lines',
        'coverage_percentage',
        'line_coverage_data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coverage_percentage' => 'decimal:2',
        ];
    }

    public function coverageReport(): BelongsTo
    {
        return $this->belongsTo(CoverageReport::class);
    }

    /**
     * @return Attribute<array<int, array{covered: bool, count: int}>, never>
     */
    protected function lineCoverage(): Attribute
    {
        return Attribute::make(
            get: fn () => json_decode(gzuncompress(base64_decode($this->line_coverage_data)), true),
        );
    }
}
