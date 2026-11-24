<?php
namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ShikimoriClient
{
    private const BASE = 'https://shikimori.one/api/';

    /** Make a real HTTPS request. Never disable certificate verification. */
    public function request(string $path, array $query = []): array
    {
        $ca = config('services.shikimori.ca_bundle');
        if ($ca && !is_readable($ca)) {
            throw new ShikimoriException('certificate', 'Configured CA bundle is not readable.');
        }
        $timeout = max(3, min(30, (int) config('services.shikimori.timeout', 9)));
        $delay = max(0, min(5000, (int) config('services.shikimori.retry_delay_ms', 1200)));
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $request = Http::acceptJson()->withHeaders(['Cache-Control' => 'no-cache'])->withUserAgent('AnimeScope/3.1 (Laravel educational project)')
                    ->connectTimeout(6)->timeout($timeout);
                if ($ca) {
                    $request = $request->withOptions(['verify' => $ca]);
                }
                $url = self::BASE.$path.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
                $response = $request->get($url);
            } catch (ConnectionException $e) {
                $message = $e->getMessage();
                $reason = match (true) {
                    (bool) preg_match('/cURL error (60|77)\b|certificate/i', $message) => 'certificate',
                    (bool) preg_match('/cURL error (5|6)\b|resolve host/i', $message) => 'dns',
                    (bool) preg_match('/cURL error 28\b|timed out/i', $message) => 'timeout',
                    default => 'connection',
                };
                if ($attempt === 0 && !in_array($reason, ['certificate', 'dns'], true)) {
                    usleep($delay * 1000);
                    continue;
                }
                throw new ShikimoriException($reason, $message);
            }
            $status = $response->status();
            if ($response->successful()) {
                $json = $response->json();
                if (is_array($json)) {
                    $valid = str_starts_with($path, 'animes/') ? isset($json['id'], $json['name']) : array_is_list($json);
                    if ($valid) return $json;
                }
                throw new ShikimoriException('invalid_response', 'Shikimori returned an unexpected response.', $status);
            }
            if ($attempt === 0 && ($status === 429 || $status >= 500)) {
                $retryAfter = (int) $response->header('Retry-After');
                usleep(max($delay, min(5000, $retryAfter * 1000)) * 1000);
                continue;
            }
            throw new ShikimoriException(match (true) {
                $status === 404 => 'not_found',
                $status === 429 => 'rate_limit',
                $status >= 500 => 'upstream',
                default => 'http_error',
            }, 'Shikimori returned HTTP '.$status, $status);
        }
        throw new ShikimoriException('connection', 'Shikimori request failed.');
    }

    /** Keep successful data for seven days, revalidate after ten minutes. */
    public function fetch(string $path, array $query = [], bool $refresh = false): array
    {
        ksort($query);
        $key = 'shikimori.v1.'.hash('sha256', $path.'?'.http_build_query($query));
        $cached = Cache::get($key);
        if (!$refresh && is_array($cached) && time() - $cached['timestamp'] < 600) {
            return ['payload' => $cached['payload'], 'source' => 'cache', 'fetched_at' => $cached['timestamp'], 'reason' => null];
        }
        try {
            $payload = $this->request($path, $query);
            $timestamp = time();
            Cache::put($key, compact('payload', 'timestamp'), now()->addDays(7));
            return ['payload' => $payload, 'source' => 'live', 'fetched_at' => $timestamp, 'reason' => null];
        } catch (ShikimoriException $e) {
            Log::warning('Shikimori request failed', ['path' => $path, 'reason' => $e->reason, 'status' => $e->httpStatus, 'message' => $e->getMessage()]);
            if ($e->reason !== 'not_found' && is_array($cached)) {
                return ['payload' => $cached['payload'], 'source' => 'stale', 'fetched_at' => $cached['timestamp'], 'reason' => $e->reason];
            }
            throw $e;
        }
    }
}
