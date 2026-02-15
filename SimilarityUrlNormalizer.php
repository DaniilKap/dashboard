<?php

namespace backend\modules\notary\services;

final class SimilarityUrlNormalizer
{
    /**
     * Normalize URL for stable matching and deduplication.
     *
     * Rules:
     * - trim and remove fragment (#...)
     * - ensure scheme exists (default https)
     * - normalize scheme to lowercase http/https
     * - normalize host to lowercase
     * - remove trailing slash in path (except root)
     * - optional query cleanup:
     *   - drop full query string
     *   - or remove only UTM params
     */
    public function normalizeUrlForMatch(string $url, bool $stripQuery = false, bool $stripUtm = true): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $hashPos = strpos($url, '#');
        if ($hashPos !== false) {
            $url = substr($url, 0, $hashPos);
        }

        if (!preg_match('~^[a-z][a-z0-9+\-.]*://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
        if (!in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'https';
        }

        $host = strtolower((string)$parts['host']);
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';

        $path = (string)($parts['path'] ?? '');
        if ($path !== '' && $path !== '/') {
            $path = rtrim($path, '/');
            if ($path === '') {
                $path = '/';
            }
        }

        $query = (string)($parts['query'] ?? '');
        if ($stripQuery) {
            $query = '';
        } elseif ($stripUtm && $query !== '') {
            parse_str($query, $queryParams);
            if (is_array($queryParams)) {
                foreach (array_keys($queryParams) as $key) {
                    if (stripos((string)$key, 'utm_') === 0) {
                        unset($queryParams[$key]);
                    }
                }
                $query = http_build_query($queryParams);
            }
        }

        return $scheme . '://' . $host . $port . $path . ($query !== '' ? '?' . $query : '');
    }

    public function extractDomain(?string $url): ?string
    {
        if (!is_string($url) || trim($url) === '') {
            return null;
        }

        $normalized = $this->normalizeUrlForMatch($url);
        if ($normalized === '') {
            return null;
        }

        $host = parse_url($normalized, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        return strtolower($host);
    }
}
