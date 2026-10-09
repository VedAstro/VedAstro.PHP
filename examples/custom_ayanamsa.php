<?php

declare(strict_types=1);

/**
 * DEMO: Choosing an ayanamsa.
 *
 * USE CASE: compare the same chart under different ayanamsa systems
 * DIFFICULTY: Intermediate
 *
 * The ayanamsa is the gap between the tropical and sidereal zodiacs, and the choice can shift a
 * planet by up to about three degrees - enough to change its sign near a boundary.
 *
 * RUN: php examples/custom_ayanamsa.php
 */

require_once __DIR__ . '/support.php';

use VedAstro\Generated\Ayanamsa;
use VedAstro\Response;

$client = demoClient();
$birth = demoBirth();

echo 'Ayanamsa degree at birth, by system:', PHP_EOL;

foreach ([Ayanamsa::LAHIRI, Ayanamsa::RAMAN, Ayanamsa::KRISHNAMURTI, Ayanamsa::FAGAN_BRADLEY] as $ayanamsa) {
    // withAyanamsa scopes the choice to this call and restores the previous setting after,
    // so nothing leaks into later requests.
    $degree = $client->withAyanamsa(
        $ayanamsa,
        static fn () => $client->calculate()->ayanamsaDegree($birth)
    );
    printf(
        "  %-16s %s%s",
        $ayanamsa->value,
        Response::string($degree, 'DegreeMinuteSecond', '-'),
        PHP_EOL
    );
    demoPace();
}

// A CLIENT-WIDE DEFAULT
// Set it once when the whole application uses one system:
//
//   $client = new VedAstroClient([
//       'apiKey' => $apiKey,
//       'defaultAyanamsa' => Ayanamsa::LAHIRI,
//   ]);
//
// Omit it entirely to accept the server default, which measures as Lahiri.
