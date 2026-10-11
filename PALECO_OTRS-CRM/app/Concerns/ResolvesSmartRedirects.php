<?php

namespace App\Concerns;

use Illuminate\Http\Request;

/**
 * Trait to provide uniform smart navigation and origin-aware redirection across controllers.
 * Inspects incoming request inputs and query strings for 'source' and returns contextual URLs.
 */
trait ResolvesSmartRedirects
{
    /**
     * Resolves the appropriate destination URL based on source query parameters or fallback route.
     *
     * @param  Request  $request
     * @param  string  $fallbackRoute
     * @param  array<string, string|callable|array>  $sourceRouteMap  Keyed by source identifier (e.g. 'dashboard', 'details', 'department')
     * @param  array  $defaultParams
     * @return string Absolute or relative URL to redirect to
     */
    protected function resolveSmartRedirect(
        Request $request,
        string $fallbackRoute,
        array $sourceRouteMap = [],
        array $defaultParams = []
    ): string {
        $source = $request->input('source', $request->query('source'));

        if ($source && isset($sourceRouteMap[$source])) {
            $target = $sourceRouteMap[$source];

            if (is_callable($target)) {
                return (string) $target($request);
            }

            if (is_array($target)) {
                return route($target[0], $target[1] ?? []);
            }

            return route($target, $defaultParams);
        }

        return route($fallbackRoute, $defaultParams);
    }
}

