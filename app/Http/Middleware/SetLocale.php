<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_map('strtolower', (array) config('app.supported_locales', ['en']));
        $default   = strtolower((string) config('app.locale', 'en'));

        $locale = $this->resolve($request, $supported, $default);

        if ($locale !== null) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    /**
     * Resolution order:
     *   1. Accept-Language (parsed, q-weighted, regional subtags normalized, q=0 skipped)
     *   2. Application default locale (config('app.locale'))
     *
     * No X-Locale header. No ?locale query. No stored user preference.
     *
     * @param  array<int, string>  $supported  Lower-cased locale tags, e.g. ['en', 'ar'].
     */
    private function resolve(Request $request, array $supported, string $default): ?string
    {
        $header = (string) $request->header('Accept-Language', '');

        if ($header !== '') {
            foreach ($this->parseAcceptLanguage($header) as $tag) {
                $normalized = $this->normalize($tag);

                if ($normalized !== null && in_array($normalized, $supported, true)) {
                    return $normalized;
                }
            }
        }

        return in_array($default, $supported, true) ? $default : null;
    }

    /**
     * @return array<int, string>  Tags ordered by descending q-value.
     */
    private function parseAcceptLanguage(string $header): array
    {
        $parts = explode(',', $header);
        $parsed = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $segments = explode(';', $part, 2);
            $tag = trim($segments[0]);
            $q   = 1.0;

            if (isset($segments[1]) && preg_match('/q=([0-9.]+)/', trim($segments[1]), $m) === 1) {
                $q = (float) $m[1];
            }

            // q=0 means "explicitly not acceptable" per RFC 7231.
            if ($q <= 0.0) {
                continue;
            }

            if ($tag !== '') {
                $parsed[] = ['tag' => $tag, 'q' => $q];
            }
        }

        usort($parsed, static fn (array $a, array $b) => $b['q'] <=> $a['q']);

        return array_map(static fn (array $p) => $p['tag'], $parsed);
    }

    private function normalize(string $tag): ?string
    {
        $tag = strtolower(trim($tag));

        if ($tag === '' || $tag === '*') {
            return null;
        }

        $base = explode('-', $tag, 2)[0];

        return $base === '' ? null : $base;
    }
}
