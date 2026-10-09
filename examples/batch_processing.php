<?php

declare(strict_types=1);

/**
 * DEMO: Batch processing many charts.
 *
 * USE CASE: look up many charts without tripping the rate limit
 * DIFFICULTY: Advanced
 *
 * RUN: php examples/batch_processing.php
 */

require_once __DIR__ . '/support.php';

use VedAstro\ApiException;
use VedAstro\Generated\PlanetName;
use VedAstro\GeoLocation;
use VedAstro\Response;
use VedAstro\Time;
use VedAstro\VedAstroClient;
use VedAstro\VedAstroException;

$client = demoClient();

$people = [
    ['name' => 'Mumbai', 'time' => '14:30 25/10/1992 +05:30', 'longitude' => 72.8777, 'latitude' => 19.0760],
    ['name' => 'Tokyo', 'time' => '23:40 31/12/2010 +08:00', 'longitude' => 139.83, 'latitude' => 35.65],
    ['name' => 'New York', 'time' => '09:00 15/06/1997 -05:00', 'longitude' => -74.006, 'latitude' => 40.7128],
    ['name' => 'London', 'time' => '06:15 03/03/2001 +00:00', 'longitude' => -0.1278, 'latitude' => 51.5074],
];

$results = [];

foreach ($people as $person) {
    $birth = Time::of($person['time'], GeoLocation::of($person['name'], $person['longitude'], $person['latitude']));

    // A cache is the single biggest saving here: the same chart input recurs constantly in
    // production, and a hit costs nothing upstream.
    $cacheKey = 'vedastro:sun:' . md5($person['time'] . $person['name']);
    if (isset($results[$cacheKey])) {
        continue;
    }

    try {
        $sun = fetchWithRetry(
            static fn () => $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth)
        );
        $results[$cacheKey] = Response::string($sun, 'Name', 'unknown');
        printf("%-10s Sun in %s%s", $person['name'], $results[$cacheKey], PHP_EOL);
    } catch (ApiException|VedAstroException $failure) {
        printf("%-10s failed: %s%s", $person['name'], $failure->getMessage(), PHP_EOL);
    }

    demoPace();
}

echo PHP_EOL, 'Processed ', count($results), ' charts.', PHP_EOL;

/**
 * Retries an idempotent read on a transport failure.
 *
 * Never retry an {@see ApiException}: that means the API answered and rejected the request, so
 * repeating it only spends another call.
 */
function fetchWithRetry(callable $action, int $attempts = 3): mixed
{
    for ($attempt = 1; ; $attempt++) {
        try {
            return $action();
        } catch (VedAstroException $failure) {
            if ($attempt >= $attempts) {
                throw $failure;
            }
            sleep(1);
        }
    }
}

// SCALING THIS UP
// - PHP is single-threaded per process, so raise concurrency with workers rather than threads:
//   run several queue consumers, or use curl_multi through a custom Transport implementation.
// - Whatever the fan-out, the rate limit is counted per API key across every process, so pace
//   centrally (a shared token bucket in Redis) rather than per worker.
// - Checkpoint results as you go; a long job that restarts should not re-spend its calls.
