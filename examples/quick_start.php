<?php

declare(strict_types=1);

/**
 * DEMO: Quick Start - your first Vedic astrology calculation.
 *
 * USE CASE: learn the absolute basics in a few lines of code
 * DIFFICULTY: Beginner
 *
 * RUN:
 *   php examples/quick_start.php
 *
 * EXPECTED OUTPUT:
 *   Sun Sign: Libra
 */

require_once __DIR__ . '/support.php';

use VedAstro\Calculate;
use VedAstro\Generated\PlanetName;
use VedAstro\GeoLocation;
use VedAstro\Response;
use VedAstro\Time;

// Step 1: point the static facade at an API key.
// Free tier: 'FreeAPIUser' (5 requests per minute).
// Paid tier: read it from the environment, never from source.
Calculate::setApiKey(getenv('VEDASTRO_API_KEY') ?: 'FreeAPIUser');
// The demos also honour VEDASTRO_BASE_URL, so this keeps the facade on the same server.
demoConfigureFacade();

// Step 2: define the birth time and location.
// Time format: "HH:MM DD/MM/YYYY +TZ:TZ".
// GeoLocation takes the name, then LONGITUDE, then latitude.
$birth = Time::of('14:30 25/10/1992 +05:30', GeoLocation::of('Mumbai', 72.8777, 19.0760));

// Step 3: make the calculation. The facade forwards to a shared client.
$sun = Calculate::planetRasiD1Sign(PlanetName::SUN, $birth);

// Step 4: read the field and print it.
echo 'Sun Sign: ', Response::string($sun, 'Name'), PHP_EOL;

// NEXT STEPS
// - Moon sign:  Calculate::planetRasiD1Sign(PlanetName::MOON, $birth)
// - Ascendant:  Calculate::houseSignName(HouseName::H1, $birth)
// - More examples: see README.md
