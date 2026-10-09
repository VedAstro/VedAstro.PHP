<?php

declare(strict_types=1);

/**
 * DEMO: Birth chart basics - the "big three" plus the birth constellation.
 *
 * USE CASE: display the Sun, Moon and Ascendant signs for a chart
 * DIFFICULTY: Beginner
 *
 * RUN: php examples/birth_chart_basics.php
 */

require_once __DIR__ . '/support.php';

use VedAstro\Generated\HouseName;
use VedAstro\Generated\PlanetName;
use VedAstro\Response;

$client = demoClient();
$birth = demoBirth();

// Ask the calculator for the planet's sign.
$sun = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);
echo 'Sun:    ', demoField($sun, 'Name'), PHP_EOL;

demoPace();
$moon = $client->calculate()->planetRasiD1Sign(PlanetName::MOON, $birth);
echo 'Moon:   ', demoField($moon, 'Name'), PHP_EOL;

demoPace();
// HouseSignName takes a house constant; house 1 is the ascendant, and it unwraps to a string.
$rising = $client->calculate()->houseSignName(HouseName::H1, $birth);
echo 'Rising: ', $rising, PHP_EOL;

demoPace();
// The nakshatra the Moon occupied at birth, used for naming and compatibility.
$constellation = $client->calculate()->moonConstellation($birth);
echo 'Birth constellation: ', $constellation, PHP_EOL;

demoPace();
// The whole Rasi chart in one call: every planet's sign and degree.
$rasiSigns = $client->calculate()->allPlanetRasiSigns($birth);
echo 'Planets in Rasi:', PHP_EOL;
foreach (Response::list($rasiSigns) as $name => $entry) {
    echo '  ', $name, ': ', Response::string($entry, 'Name', '-'), PHP_EOL;
}
