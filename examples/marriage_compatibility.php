<?php

declare(strict_types=1);

/**
 * DEMO: Marriage compatibility - the 16-factor Kuta analysis.
 *
 * USE CASE: check whether two birth charts are considered compatible
 * DIFFICULTY: Beginner
 *
 * RUN: php examples/marriage_compatibility.php
 */

require_once __DIR__ . '/support.php';

use VedAstro\GeoLocation;
use VedAstro\Response;
use VedAstro\Time;

$client = demoClient();

$person1 = Time::of('23:40 31/12/1996 +09:00', GeoLocation::of('Tokyo', 139.83, 35.65));
$person2 = Time::of('14:30 15/06/1997 -05:00', GeoLocation::of('New York', -74.006, 40.7128));

echo 'Calculating compatibility... (this may take a few seconds)', PHP_EOL;

$report = $client->calculate()->matchReport($person1, $person2);

// KutaScore is normalised to 100; the classical scale is out of 36.
printf('Overall compatibility: %.0f/100%s', Response::float($report, 'KutaScore'), PHP_EOL);
echo 'Status: ', Response::string($report, ['Summary', 'ScoreSummary'], '-'), PHP_EOL;

$predictions = Response::list($report, 'PredictionList');
demoHeading(count($predictions) . '-factor Kuta analysis');

foreach ($predictions as $kuta) {
    $nature = Response::string($kuta, 'Nature', '?');
    $marker = match ($nature) {
        'Good' => '[ok]',
        'Bad' => '[  ]',
        default => '[ ?? ]',
    };
    echo '  ', $marker, ' ', Response::string($kuta, 'Name', '-'), ': ', $nature, PHP_EOL;
}

// INTERPRETING THE SCORE (classical 36-point scale, normalised to 100 here)
//   33-36 excellent    25-32 good    18-24 average    below 18 poor
// Nadi Kuta (health and progeny) is traditionally weighted most heavily.
// A low score indicates challenges, not destiny - treat it as one input.
