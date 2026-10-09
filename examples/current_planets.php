<?php

declare(strict_types=1);

/**
 * DEMO: Current planetary positions.
 *
 * USE CASE: show where every planet sits right now for a given place
 * DIFFICULTY: Beginner
 *
 * RUN: php examples/current_planets.php
 */

require_once __DIR__ . '/support.php';

use VedAstro\Generated\PlanetName;
use VedAstro\GeoLocation;
use VedAstro\Response;
use VedAstro\Time;

$client = demoClient();

$mumbai = GeoLocation::of('Mumbai', 72.8777, 19.0760);
// Time::now() builds the moment in the given zone, preserving its offset.
$now = Time::now($mumbai, new DateTimeZone('Asia/Kolkata'));

$planets = [
    PlanetName::SUN,
    PlanetName::MOON,
    PlanetName::MARS,
    PlanetName::MERCURY,
    PlanetName::JUPITER,
    PlanetName::VENUS,
    PlanetName::SATURN,
    PlanetName::RAHU,
    PlanetName::KETU,
];

echo 'Current planetary positions (', $now->stdTime, '):', PHP_EOL;
foreach ($planets as $planet) {
    // One call returns everything about the planet, so this is cheaper than two.
    $data = $client->calculate()->allPlanetData($planet, $now);
    printf(
        "  %-8s %-12s %s%s",
        $planet->value,
        Response::string($data, ['PlanetRasiD1Sign', 'Name'], '-'),
        Response::string($data, ['PlanetConstellation', 'Name'], '-'),
        PHP_EOL
    );
    demoPace();
}
