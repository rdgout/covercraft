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
        $value = $coverage ?? '?';
        $color = $this->getColor($coverage);

        $labelWidth = $this->calculateTextWidth($label);
        $valueWidth = $this->calculateTextWidth($value);
        $totalWidth = $labelWidth + $valueWidth;
        $labelX = $labelWidth / 2;
        $valueX = $labelWidth + ($valueWidth / 2);

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$totalWidth}" height="20">
            <linearGradient id="b" x2="0" y2="100%">
                <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
                <stop offset="1" stop-opacity=".1"/>
            </linearGradient>
            <mask id="a">
                <rect width="{$totalWidth}" height="20" rx="3" fill="#fff"/>
            </mask>
            <g mask="url(#a)">
                <path fill="#555" d="M0 0h{$labelWidth}v20H0z"/>
                <path fill="{$color}" d="M{$labelWidth} 0h{$valueWidth}v20H{$labelWidth}z"/>
                <path fill="url(#b)" d="M0 0h{$totalWidth}v20H0z"/>
            </g>
            <g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="11">
                <text x="{$labelX}" y="14" fill="#010101" fill-opacity=".3">{$label}</text>
                <text x="{$labelX}" y="13">{$label}</text>
                <text x="{$valueX}" y="14" fill="#010101" fill-opacity=".3">{$value}</text>
                <text x="{$valueX}" y="13">{$value}</text>
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
            return '#9f9f9f';
        }

        $value = (float) $coverage;

        return match (true) {
            $value >= 90 => '#4c1',
            $value >= 80 => '#97ca00',
            $value >= 70 => '#a4a61d',
            $value >= 60 => '#dfb317',
            default => '#e05d44',
        };
    }

    private function calculateTextWidth(string $text): int
    {
        return strlen($text) * 7 + 10;
    }
}
