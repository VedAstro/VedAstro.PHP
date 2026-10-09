<?php

declare(strict_types=1);

/**
 * DEMO: Numerology - birth, destiny and Chaldean name numbers.
 *
 * USE CASE: numerology report for a name and a birth date
 * DIFFICULTY: Beginner
 *
 * RUN: php examples/numerology.php
 */

require_once __DIR__ . '/support.php';

use VedAstro\Response;

$client = demoClient();
$birth = demoBirth();
$name = 'John Doe';

demoHeading('Numerology for ' . $name);

// Numbers derived from the birth date alone.
echo 'Birth number:   ', $client->calculate()->birthNumber($birth), PHP_EOL;
demoPace();
echo 'Destiny number: ', $client->calculate()->destinyNumber($birth), PHP_EOL;
demoPace();

// Name number (Chaldean), plus the ruling planet and an interpretation.
$prediction = $client->calculate()->nameNumberPrediction($name);
echo 'Name:           ', $name, PHP_EOL;
echo 'Number:         ', Response::get($prediction, 'Number'), PHP_EOL;
echo 'Root number:    ', Response::get($prediction, 'RootNumber'), PHP_EOL;
echo 'Ruling planet:  ', Response::string($prediction, 'Planet', '-'), PHP_EOL;

// Prediction is HTML formatted, so strip the tags and trim it for a terminal.
$plain = strip_tags(Response::string($prediction, 'Prediction'));
echo 'Interpretation: ', demoTruncate($plain, 400), PHP_EOL;

// COMPARING SPELLINGS
// Each name variant costs one API call, so pace them on the free tier.
demoPace();
$alternative = $client->calculate()->nameNumberPrediction('Jon Doe');
printf(
    "  %-10s number %s (root %s, ruling planet %s)%s",
    'Jon Doe',
    (string) Response::get($alternative, 'Number'),
    (string) Response::get($alternative, 'RootNumber'),
    Response::string($alternative, 'Planet', '-'),
    PHP_EOL
);
