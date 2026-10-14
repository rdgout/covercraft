<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\Response;

class BadgeController extends Controller
{
    public function show(string $owner, string $name, string $branch): Response
    {
        $repository = Repository::query()
            ->where('owner', $owner)
            ->where('name', $name)
            ->first();

        if (! $repository) {
            return $this->generateBadge(null);
        }

        $coverage = $repository->coverageReports()
            ->where('branch', $branch)
            ->where('archived', false)
            ->where('status', 'completed')
            ->latest()
            ->value('coverage_percentage');

        return $this->generateBadge($coverage);
    }

    private function generateBadge(?string $coverage): Response
    {
        $label = 'coverage';
        $value = $coverage === null ? '?' : number_format((float) $coverage, 1).'%';
        $color = $this->getColor($coverage);

        $labelWidth = $this->calculateTextWidth($label);
        $valueWidth = $this->calculateTextWidth($value);
        $totalWidth = $labelWidth + $valueWidth;
        $labelX = $labelWidth / 2;
        $valueX = $labelWidth + ($valueWidth / 2);

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$totalWidth}" height="20" viewBox="0 0 {$totalWidth} 20" role="img" aria-label="{$label}: {$value}">
            <title>{$label}: {$value}</title>
            <clipPath id="badge">
                <rect width="{$totalWidth}" height="20" rx="4"/>
            </clipPath>
            <g clip-path="url(#badge)">
                <path fill="#0d1c3c" d="M0 0h{$labelWidth}v20H0z"/>
                <path fill="{$color}" d="M{$labelWidth} 0h{$valueWidth}v20H{$labelWidth}z"/>
            </g>
            <g text-anchor="middle" font-family="Figtree,Arial,sans-serif" font-size="11" font-weight="700">
                <text x="{$labelX}" y="14" fill="#ffffff">{$label}</text>
                <text x="{$valueX}" y="14" fill="#0d1c3c">{$value}</text>
            </g>
        </svg>
        SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }

    private function getColor(?string $coverage): string
    {
        if ($coverage === null) {
            return '#eaf1f5';
        }

        $value = (float) $coverage;

        return match (true) {
            $value >= 80 => '#72cad7',
            $value >= 50 => '#ffc94a',
            default => '#ff7a59',
        };
    }

    private function calculateTextWidth(string $text): int
    {
        return strlen($text) * 6 + 14;
    }
}
