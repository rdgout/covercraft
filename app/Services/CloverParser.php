<?php

namespace App\Services;

use App\Exceptions\FileNotFoundException;
use App\Exceptions\InvalidCloverFormatException;
use SimpleXMLElement;

class CloverParser
{
    /**
     * @return array{overall_percentage: float, total_lines: int, covered_lines: int, files: list<array{path: string, total_lines: int, covered_lines: int, percentage: float, lines: array<int, array{covered: bool, count: int}>}>}
     */
    public function parse(string $filePath): array
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

        $files = [];
        $totalLines = 0;
        $coveredLines = 0;

        foreach ($xml->xpath('//file') as $fileNode) {
            $fileData = $this->parseFile($fileNode);
            $files[] = $fileData;
            $totalLines += $fileData['total_lines'];
            $coveredLines += $fileData['covered_lines'];
        }

        return [
            'overall_percentage' => $totalLines > 0
                ? round(($coveredLines / $totalLines) * 100, 2)
                : 0.0,
            'total_lines' => $totalLines,
            'covered_lines' => $coveredLines,
            'files' => $files,
        ];
    }

    /**
     * @return array{path: string, total_lines: int, covered_lines: int, percentage: float, lines: array<int, array{covered: bool, count: int}>}
     */
    private function parseFile(SimpleXMLElement $fileNode): array
    {
        $filePath = (string) $fileNode['name'];
        $lines = [];
        $totalLines = 0;
        $coveredLines = 0;

        foreach ($fileNode->line as $lineNode) {
            $type = (string) $lineNode['type'];

            if ($type !== 'stmt') {
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
            'percentage' => $totalLines > 0
                ? round(($coveredLines / $totalLines) * 100, 2)
                : 0.0,
            'lines' => $lines,
        ];
    }
}
