<?php

namespace Database\Factories;

use App\Models\CoverageFile;
use App\Models\CoverageReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoverageFile>
 */
class CoverageFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalLines = fake()->numberBetween(10, 500);
        $coveredLines = fake()->numberBetween(0, $totalLines);
        $percentage = $totalLines > 0
            ? round(($coveredLines / $totalLines) * 100, 2)
            : 0.00;

        return [
            'coverage_report_id' => CoverageReport::factory(),
            'file_path' => 'src/'.fake()->slug(2).'.php',
            'total_lines' => $totalLines,
            'covered_lines' => $coveredLines,
            'coverage_percentage' => $percentage,
            'line_coverage_data' => gzcompress(json_encode([])),
        ];
    }

    public function withLineCoverage(): static
    {
        return $this->state(function (array $attributes) {
            $totalLines = $attributes['total_lines'];
            $coveredLines = $attributes['covered_lines'];
            $lineData = [];
            $covered = 0;

            for ($i = 1; $i <= $totalLines; $i++) {
                $isCovered = $covered < $coveredLines && fake()->boolean(70);
                if ($isCovered) {
                    $covered++;
                }

                $lineData[$i] = [
                    'covered' => $isCovered,
                    'count' => $isCovered ? fake()->numberBetween(1, 50) : 0,
                ];
            }

            return [
                'line_coverage_data' => gzcompress(json_encode($lineData)),
            ];
        });
    }
}
