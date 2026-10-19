<?php

namespace App\Presenters;

/**
 * Presenter responsible for computing SVG donut chart geometric arc paths, angles, and percentages.
 * Isolates presentation trigonometry from backend business aggregation services.
 */
class SvgDonutPresenter
{
    /**
     * Computes SVG arc path strings and slice percentages for an array of status slice metrics.
     *
     * @param  array  $statuses Array of slice items containing 'key', 'label', 'total', etc.
     * @param  int  $actualTotal Sum of all slice totals.
     * @param  int  $cx Center X coordinate (default 100).
     * @param  int  $cy Center Y coordinate (default 100).
     * @param  int  $outerR Outer radius (default 86).
     * @param  int  $innerR Inner radius (default 50).
     * @return array Slices enriched with 'width_percent', 'start_percent', 'end_percent', and 'path_d'.
     */
    public static function computeGeometry(
        array $statuses,
        int $actualTotal,
        int $cx = 100,
        int $cy = 100,
        int $outerR = 86,
        int $innerR = 50
    ): array {
        $totalTickets = max(1, $actualTotal);
        $accumulatedPercent = 0.0;

        return collect($statuses)->map(function ($status) use (&$accumulatedPercent, $totalTickets, $actualTotal, $cx, $cy, $outerR, $innerR) {
            $slicePercent = ($status['total'] / $totalTickets) * 100;
            $status['width_percent'] = round($slicePercent, 1);
            $status['filter_url'] = (function_exists('route') && app()->has('url'))
                ? route('cwd.tickets', ['status' => $status['key']])
                : null;

            if ($actualTotal === 0 || $status['total'] === 0) {
                $status['path_d'] = '';
                $status['start_percent'] = 0;
                $status['end_percent'] = 0;

                return $status;
            }

            $startPercent = $accumulatedPercent;
            $endPercent = $startPercent + $slicePercent;
            $accumulatedPercent = $endPercent;

            $status['start_percent'] = round($startPercent, 2);
            $status['end_percent'] = round($endPercent, 2);

            if ($slicePercent >= 99.99) {
                $status['path_d'] = sprintf(
                    'M %s %s A %s %s 0 1 1 %s %s A %s %s 0 1 1 %s %s M %s %s A %s %s 0 1 0 %s %s A %s %s 0 1 0 %s %s Z',
                    $cx, $cy - $outerR, $outerR, $outerR, $cx, $cy + $outerR, $outerR, $outerR, $cx, $cy - $outerR,
                    $cx, $cy - $innerR, $innerR, $innerR, $cx, $cy + $innerR, $innerR, $innerR, $cx, $cy - $innerR
                );
            } else {
                $startAngle = ($startPercent / 100) * 2 * M_PI;
                $endAngle = ($endPercent / 100) * 2 * M_PI;

                $x1 = round($cx + $outerR * sin($startAngle), 3);
                $y1 = round($cy - $outerR * cos($startAngle), 3);
                $x2 = round($cx + $outerR * sin($endAngle), 3);
                $y2 = round($cy - $outerR * cos($endAngle), 3);

                $x3 = round($cx + $innerR * sin($endAngle), 3);
                $y3 = round($cy - $innerR * cos($endAngle), 3);
                $x4 = round($cx + $innerR * sin($startAngle), 3);
                $y4 = round($cy - $innerR * cos($startAngle), 3);

                $largeArc = ($endAngle - $startAngle) > M_PI ? 1 : 0;

                $status['path_d'] = sprintf(
                    'M %s %s A %s %s 0 %d 1 %s %s L %s %s A %s %s 0 %d 0 %s %s Z',
                    $x1, $y1, $outerR, $outerR, $largeArc, $x2, $y2,
                    $x3, $y3, $innerR, $innerR, $largeArc, $x4, $y4
                );
            }

            return $status;
        })->all();
    }
}
