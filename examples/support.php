<?php

declare(strict_types=1);

/**
 * Shared helpers for the demo scripts.
 *
 * Application code does not need this file. The important part is {@see demoClient()}, which
 * shows the recommended way to build one client and reuse it.
 */

// The demos load the SDK straight from the repository, so either autoloader works.
require_once __DIR__ . '/../src/autoload.php';

use VedAstro\GeoLocation;
use VedAstro\Response;
use VedAstro\Time;
use VedAstro\VedAstroClient;

/** The free-tier pacing gap. Five requests per minute means one every twelve seconds. */
const DEMO_FREE_TIER_GAP = 13.0;

/**
 * Builds one client for the whole script.
 *
 * A client is stateless between calls, so create it once rather than per call. The demo scripts
 * honour `VEDASTRO_BASE_URL` so they can be pointed at a test server, and
 * `VEDASTRO_API_KEY` for a paid key.
 *
 * @param bool $silent Suppress the start-up banner. Library and web-handler code should, because
 *                     the banner writes to the output stream.
 */
function demoClient(bool $silent = false): VedAstroClient
{
    $key = getenv('VEDASTRO_API_KEY');
    $baseUrl = getenv('VEDASTRO_BASE_URL');

    $options = [
        'apiKey' => ($key === false || trim($key) === '') ? 'FreeAPIUser' : $key,
        'silent' => $silent,
    ];
    if ($baseUrl !== false && trim($baseUrl) !== '') {
        $options['baseUrl'] = $baseUrl;
    }

    return new VedAstroClient($options);
}

/**
 * Wires the static {@see Calculate} facade to the same configuration, so both styles reach the
 * intended server. Call this before the first facade call.
 */
function demoConfigureFacade(): void
{
    VedAstro\Calculate::setClient(demoClient());
}

/** Reports whether the client is on the free tier, which needs pacing. */
function demoFreeTier(): bool
{
    $key = getenv('VEDASTRO_API_KEY');

    return $key === false || trim($key) === '';
}

/** Waits between calls so the free tier's rate limit is never reached. */
function demoPace(): void
{
    if (demoFreeTier()) {
        usleep((int) (DEMO_FREE_TIER_GAP * 1_000_000));
    }
}

/** The sample chart every demo uses. */
function demoBirth(): Time
{
    return Time::of('14:30 25/10/1992 +05:30', GeoLocation::of('Mumbai', 72.8777, 19.0760));
}

/** Reads a field from an object payload, or returns a placeholder. */
function demoField(mixed $payload, string $key): string
{
    return Response::string($payload, $key, '(missing)');
}

/**
 * Truncates text for display without requiring the mbstring extension.
 *
 * Falls back to a plain byte slice when the text is not valid UTF-8, so a demo never crashes on
 * a missing extension.
 */
function demoTruncate(string $text, int $length = 200): string
{
    if (strlen($text) <= $length) {
        return $text;
    }

    if (preg_match('/^.{0,' . $length . '}/us', $text, $matches) === 1) {
        return $matches[0];
    }

    return substr($text, 0, $length);
}

/** Prints a heading, so the demo output is easy to scan. */
function demoHeading(string $text): void
{
    echo PHP_EOL, $text, PHP_EOL, str_repeat('-', strlen($text)), PHP_EOL;
}
