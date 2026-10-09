<?php

declare(strict_types=1);

/**
 * DEMO: Error handling.
 *
 * USE CASE: handle every failure mode a production integration will meet
 * DIFFICULTY: Intermediate
 *
 * RUN: php examples/error_handling.php
 */

require_once __DIR__ . '/support.php';

use VedAstro\ApiException;
use VedAstro\Generated\PlanetName;
use VedAstro\VedAstroClient;
use VedAstro\VedAstroException;

$client = demoClient();
$birth = demoBirth();

// 1. Client-side validation fails immediately, before any network call.
try {
    VedAstro\Time::of('2:30 PM 25/10/1992 +05:30', $birth->location);
} catch (InvalidArgumentException $invalid) {
    echo '[validation] ', $invalid->getMessage(), PHP_EOL;
}

// 2. A normal call, guarded the way a web request handler would guard it.
try {
    $result = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);
    echo '[ok] Sun in ', demoField($result, 'Name'), PHP_EOL;
} catch (ApiException $remote) {
    // The API answered but reported a failure: bad input, bad key, or a rate limit.
    fprintf(
        STDERR,
        "[api] %s HTTP %d: %s%s",
        $remote->endpoint(),
        $remote->statusCode(),
        $remote->getMessage(),
        PHP_EOL
    );
} catch (VedAstroException $transport) {
    // No usable response: DNS, TLS, connect timeout, or a malformed body.
    echo '[transport] ', $transport->getMessage(), PHP_EOL;
}

// 3. A small helper that retries a transient failure. The SDK never retries on its own, because
//    only the caller knows whether a second request is safe.
$moon = withRetry(static fn () => $client->calculate()->planetRasiD1Sign(PlanetName::MOON, $birth));
echo '[retry ok] Moon in ', demoField($moon, 'Name'), PHP_EOL;

// 4. Inspect the status yourself when you want the raw envelope.
$response = $client->callWithResponse('AyanamsaDegree', ['time' => $birth]);
printf(
    "Envelope status: %s (success: %s)%s",
    $response->status(),
    $response->isSuccess() ? 'yes' : 'no',
    PHP_EOL
);

/**
 * Runs an action, retrying transient failures with a delay.
 *
 * Only idempotent reads are safe to retry, and only on transport failures.
 */
function withRetry(callable $action, int $attempts = 3): mixed
{
    for ($attempt = 1; ; $attempt++) {
        try {
            return $action();
        } catch (VedAstroException $failure) {
            if ($attempt >= $attempts) {
                throw $failure;
            }
            echo '   retry ', $attempt, '/', $attempts - 1, ': ', $failure->getMessage(), PHP_EOL;
            sleep(demoFreeTier() ? 13 : 1);
        }
    }
}
