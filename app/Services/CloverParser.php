<?php

namespace App\Services;

use App\Exceptions\FileNotFoundException;
use App\Exceptions\InvalidCloverFormatException;
use SimpleXMLElement;

class CloverParser
{
    /**
     * @param  list<string>  $knownRepositoryFiles
     * @return array{overall_percentage: float, total_lines: int, covered_lines: int, files: list<array{path: string, total_lines: int, covered_lines: int, percentage: float, lines: array<int, array{covered: bool, count: int}>}>}
     */
    public function parse(string $filePath, array $knownRepositoryFiles = []): array
    {
        if (! file_exists($filePath)) {
            throw new FileNotFoundException("Clover file not found: {$filePath}");
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($filePath);
        libxml_use_internal_errors($previousUseErrors);

        if ($xml === false) {
            throw new InvalidCloverFormatException('Failed to parse XML file');
        }

        $fileNodes = $xml->xpath('//file');
        $basePrefix = $this->detectBasePrefix($fileNodes, $knownRepositoryFiles);

        $files = [];
        $totalLines = 0;
        $coveredLines = 0;

        foreach ($fileNodes as $fileNode) {
            $fileData = $this->parseFile($fileNode, $basePrefix);
            $files[] = $fileData;
            $totalLines += $fileData['total_lines'];
            $coveredLines += $fileData['covered_lines'];
        }

        return [
            'overall_percentage' => $this->calculatePercentage($coveredLines, $totalLines),
            'total_lines' => $totalLines,
            'covered_lines' => $coveredLines,
            'files' => $files,
        ];
    }

    /**
     * @return array{path: string, total_lines: int, covered_lines: int, percentage: float, lines: array<int, array{covered: bool, count: int}>}
     */
    private function parseFile(SimpleXMLElement $fileNode, ?string $basePrefix): array
    {
        $filePath = (string) $fileNode['name'];

        if ($basePrefix && str_starts_with($filePath, $basePrefix)) {
            $filePath = substr($filePath, strlen($basePrefix));
        }

        $lines = [];
        $totalLines = 0;
        $coveredLines = 0;

        foreach ($fileNode->line as $lineNode) {
            if ((string) $lineNode['type'] !== 'stmt') {
                continue;
            }

            $lineNum = (int) $lineNode['num'];
            $count = (int) $lineNode['count'];
            $isCovered = $count > 0;

            $totalLines++;
            if ($isCovered) {
                $coveredLines++;
            }

            $lines[$lineNum] = [
                'covered' => $isCovered,
                'count' => $count,
            ];
        }

        return [
            'path' => $filePath,
            'total_lines' => $totalLines,
            'covered_lines' => $coveredLines,
            'percentage' => $this->calculatePercentage($coveredLines, $totalLines),
            'lines' => $lines,
        ];
    }

    /**
     * Detect the base path prefix by matching against known repository files.
     * Falls back to auto-detection if no matches found.
     *
     * @param  array<SimpleXMLElement>  $fileNodes
     * @param  list<string>  $knownRepositoryFiles
     */
    private function detectBasePrefix(array $fileNodes, array $knownRepositoryFiles): ?string
    {
        if (empty($fileNodes)) {
            return null;
        }

        $cloverPaths = array_map(fn ($node) => (string) $node['name'], $fileNodes);

        // If paths are already relative, no stripping needed
        if (! str_starts_with($cloverPaths[0], '/')) {
            return null;
        }

        // Try to match against known repository files first
        if (! empty($knownRepositoryFiles)) {
            $prefix = $this->findPrefixByMatching($cloverPaths, $knownRepositoryFiles);
            if ($prefix !== null) {
                return $prefix;
            }
        }

        // Fall back to auto-detection
        return $this->autoDetectPrefix($cloverPaths);
    }

    /**
     * Find the prefix by matching clover paths against known repository files.
     *
     * @param  list<string>  $cloverPaths
     * @param  list<string>  $knownFiles
     */
    private function findPrefixByMatching(array $cloverPaths, array $knownFiles): ?string
    {
        $prefixes = [];

        foreach ($cloverPaths as $cloverPath) {
            foreach ($knownFiles as $repoFile) {
                // Check if clover path ends with the repository file path
                if (str_ends_with($cloverPath, $repoFile)) {
                    $prefix = substr($cloverPath, 0, -strlen($repoFile));
                    $prefixes[] = $prefix;
                    break; // Found a match, move to next clover path
                }
            }
        }

        if (empty($prefixes)) {
            return null;
        }

        // Find the most common prefix
        $prefixCounts = array_count_values($prefixes);
        arsort($prefixCounts);

        return array_key_first($prefixCounts);
    }

    /**
     * Auto-detect prefix by finding longest common directory path.
     *
     * @param  list<string>  $paths
     */
    private function autoDetectPrefix(array $paths): ?string
    {
        if (empty($paths)) {
            return null;
        }

        // Find the shortest path to avoid over-stripping
        $shortestPath = $paths[0];
        foreach ($paths as $path) {
            if (strlen($path) < strlen($shortestPath)) {
                $shortestPath = $path;
            }
        }

        // Try to find common patterns (src/, app/, lib/, etc.)
        $commonRoots = ['src/', 'app/', 'lib/', 'tests/', 'test/'];

        foreach ($commonRoots as $root) {
            $pos = strpos($shortestPath, '/'.$root);
            if ($pos !== false) {
                return substr($shortestPath, 0, $pos + 1);
            }
        }

        // Fall back to finding last common directory
        $segments = explode('/', trim($shortestPath, '/'));
        if (count($segments) <= 1) {
            return null;
        }

        // Strip the last 2 segments (filename and its parent directory)
        array_pop($segments); // Remove filename
        array_pop($segments); // Remove parent directory

        return '/'.implode('/', $segments).'/';
    }

    private function calculatePercentage(int $covered, int $total): float
    {
        return $total > 0 ? round(($covered / $total) * 100, 2) : 0.0;
    }
}
