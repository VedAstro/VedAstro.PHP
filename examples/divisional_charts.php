<?php

declare(strict_types=1);

/**
 * DEMO: Divisional charts (vargas).
 *
 * USE CASE: read the same planet in D1, D9, D10 and D12
 * DIFFICULTY: Intermediate
 *
 * A varga divides each sign into finer parts, and a planet can occupy a different sign in each
 * one. D9 (Navamsha) is used for marriage and dharma, D10 (Dashamsha) for career, and D12
 * (Dwadashamsha) for parents.
 *
 * RUN: php examples/divisional_charts.php
 */

require_once __DIR__ . '/support.php';

use VedAstro\Generated\PlanetName;
use VedAstro\Response;

$client = demoClient();
$birth = demoBirth();

demoHeading('Sun sign in each divisional chart');

$d1 = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);
echo '  D1  (Rasi):         ', demoField($d1, 'Name'), PHP_EOL;
demoPace();

$d9 = $client->calculate()->planetNavamshaD9Sign(PlanetName::SUN, $birth);
echo '  D9  (Navamsha):     ', demoField($d9, 'Name'), PHP_EOL;
demoPace();

$d10 = $client->calculate()->planetDashamamshaD10Sign(PlanetName::SUN, $birth);
echo '  D10 (Dashamsha):    ', demoField($d10, 'Name'), PHP_EOL;
demoPace();

$d12 = $client->calculate()->planetDwadashamshaD12Sign(PlanetName::SUN, $birth);
echo '  D12 (Dwadashamsha): ', demoField($d12, 'Name'), PHP_EOL;
demoPace();

// Whole-chart variants return every house in a single call, which is far cheaper than looping.
$houses = $client->calculate()->allHouseNavamshaSign($birth);
demoHeading('All 12 houses in D9');
foreach (Response::list($houses) as $house => $sign) {
    echo '  ', $house, ': ', is_scalar($sign) ? (string) $sign : Response::string($sign, 'Name', '-'), PHP_EOL;
}
