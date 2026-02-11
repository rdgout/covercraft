<?php

namespace App\Services;

use App\Models\CoverageReport;

class FileTreeBuilder
{
    /**
     * @param list<string> $repositoryFiles
     * @return array<string, mixed>
     */
    public function build(CoverageReport $report, array $repositoryFiles): array
    {
        $tree = [];
        $coveredFiles = $report->files->keyBy('file_path');

        foreach ($repositoryFiles as $filePath) {
            $coverage = $coveredFiles->get($filePath);

            $parts = explode('/', $filePath);
            $current = &$tree;

            foreach ($parts as $index => $part) {
                if ($index === count($parts) - 1) {
                    $current[$part] = [
                        'type' => 'file',
                        'path' => $filePath,
                        'coverage' => $coverage ? (float) $coverage->coverage_percentage : 0.0,
                        'covered' => $coverage !== null,
                    ];
                } else {
                    if (! isset($current[$part])) {
                        $current[$part] = ['type' => 'directory', 'children' => []];
                    }
                    $current = &$current[$part]['children'];
                }
            }

            unset($current);
        }

        return $this->calculateDirectoryCoverage($tree);
    }

    /**
     * @param list<string> $files
     * @param list<string> $patterns
     * @return list<string>
     */
    public function applyExclusionPatterns(array $files, array $patterns): array
    {
        return array_values(array_filter($files, function (string $file) use ($patterns): bool {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $file)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param array<string, mixed> $tree
     * @return array<string, mixed>
     */
    public function calculateDirectoryCoverage(array $tree): array
    {
        foreach ($tree as $name => &$node) {
            if ($node['type'] === 'directory') {
                $node['children'] = $this->calculateDirectoryCoverage($node['children']);
                $stats = $this->collectDirectoryStats($node['children']);
                $node['coverage'] = $stats['total_files'] > 0
                    ? round($stats['total_coverage'] / $stats['total_files'], 2)
                    : 0.0;
                $node['file_count'] = $stats['total_files'];
            }
        }

        return $tree;
    }

    /**
     * @param array<string, mixed> $children
     * @return array{total_files: int, total_coverage: float}
     */
    private function collectDirectoryStats(array $children): array
    {
        $totalFiles = 0;
        $totalCoverage = 0.0;

        foreach ($children as $node) {
            if ($node['type'] === 'file') {
                $totalFiles++;
                $totalCoverage += $node['coverage'];
            } elseif ($node['type'] === 'directory') {
                $stats = $this->collectDirectoryStats($node['children']);
                $totalFiles += $stats['total_files'];
                $totalCoverage += $stats['total_coverage'];
            }
        }

        return [
            'total_files' => $totalFiles,
            'total_coverage' => $totalCoverage,
        ];
    }
}
