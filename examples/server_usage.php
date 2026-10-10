<?php

declare(strict_types=1);

/**
 * DEMO: Using the SDK from a web framework.
 *
 * USE CASE: register one client as a container service and inject it into request handlers
 * DIFFICULTY: Intermediate
 *
 * RUN: php examples/server_usage.php   (the handlers are called directly here)
 *
 * The same shape works for Laravel, Symfony, Slim, and plain PHP-FPM: build the client once at
 * start-up, then reuse it. Never construct a client per request - you would lose connection
 * reuse and re-read configuration on every call.
 */

require_once __DIR__ . '/support.php';

use VedAstro\ApiException;
use VedAstro\Generated\HouseName;
use VedAstro\Generated\PlanetName;
use VedAstro\GeoLocation;
use VedAstro\Response;
use VedAstro\Time;
use VedAstro\VedAstroClient;
use VedAstro\VedAstroException;

/**
 * The application's single client.
 *
 * In Laravel this would be a singleton bound in a service provider; in Symfony, a service
 * defined in services.yaml; in plain PHP, a value held on your front controller.
 */
function vedAstro(): VedAstroClient
{
    static $client = null;

    // demoClient() reads VEDASTRO_API_KEY and VEDASTRO_BASE_URL; a real application would read
    // the same values from its own configuration or container. The client is silenced because a
    // handler's output stream belongs to the response, not to the SDK.
    return $client ??= demoClient(silent: true);
}

/**
 * A request handler, the shape you would wire to a route.
 *
 * Cache the result by chart input in a real application: the same birth details recur, and a
 * cache hit costs nothing upstream.
 */
function sunSignHandler(string $standardTime, float $longitude, float $latitude): string
{
    $birth = Time::of($standardTime, GeoLocation::of('request', $longitude, $latitude));

    try {
        $result = vedAstro()->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);

        return Response::string($result, 'Name', 'unknown');
    } catch (ApiException $remote) {
        // The API answered with a failure: surface a 502 and log the endpoint, not the key.
        error_log('vedastro ' . $remote->endpoint() . ' HTTP ' . $remote->statusCode() . ': ' . $remote->getMessage());

        return 'unknown';
    } catch (VedAstroException $transport) {
        error_log('vedastro transport failure: ' . $transport->getMessage());

        return 'unknown';
    }
}

/** A handler that needs a per-request ayanamsa without touching shared state. */
function houseSignHandler(string $standardTime, float $longitude, float $latitude): string
{
    $birth = Time::of($standardTime, GeoLocation::of('request', $longitude, $latitude));

    return (string) vedAstro()->calculate()->houseSignName(HouseName::H1, $birth);
}

demoHeading('Handler: Sun sign');
echo 'Sun sign: ', sunSignHandler('14:30 25/10/1992 +05:30', 72.8777, 19.0760), PHP_EOL;

demoHeading('Handler: ascendant');
echo 'Ascendant: ', houseSignHandler('14:30 25/10/1992 +05:30', 72.8777, 19.0760), PHP_EOL;

// SERVING THIS IN PRODUCTION
// - One client per process, injected. The class is immutable and stateless between calls.
// - No request timeout is set, and that is deliberate: a calculation can legitimately take
//   minutes, so a library-imposed deadline would only truncate valid answers. Two deadlines come
//   from PHP itself - default_socket_timeout (60s, fails catchably) and max_execution_time (30s
//   under PHP-FPM, which fails UNCATCHABLY). Long calculations belong on a queue or CLI worker,
//   where max_execution_time is 0.
// - Cache by chart input with your framework's cache; charts rarely change.
// - Retry only idempotent reads, and only on transport failures - never on an ApiException,
//   which means the API actively rejected the request.
// - Never log the API key; the SDK already redacts it from every message it raises.
