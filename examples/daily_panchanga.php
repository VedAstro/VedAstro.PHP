<?php

declare(strict_types=1);

/**
 * DEMO: Daily Panchanga - the five limbs of the Vedic calendar.
 *
 * USE CASE: Tithi, Nakshatra, Yoga and Karana for today at a location
 * DIFFICULTY: Beginner
 *
 * Panchanga is reckoned from local sunrise, so the location matters.
 *
 * RUN: php examples/daily_panchanga.php
 */

require_once __DIR__ . '/support.php';

use VedAstro\GeoLocation;
use VedAstro\Response;
use VedAstro\Time;

$client = demoClient();

$mumbai = GeoLocation::of('Mumbai', 72.8777, 19.0760);
$today = Time::now($mumbai, new DateTimeZone('Asia/Kolkata'));

demoHeading('Panchanga for ' . $today->stdTime);

// LunarDay returns an object; the others unwrap to strings, so shapes differ per calculator.
$tithi = $client->calculate()->lunarDay($today);
echo 'Tithi:     ', Response::string($tithi, 'Name', '-'),
    ' (', Response::string($tithi, 'Paksha', '-'), ')', PHP_EOL;

demoPace();
echo 'Nakshatra: ', $client->calculate()->moonConstellation($today), PHP_EOL;

demoPace();
$yoga = $client->calculate()->nithyaYoga($today);
echo 'Yoga:      ', Response::string($yoga, 'Name', '-'), PHP_EOL;

demoPace();
echo 'Karana:    ', $client->calculate()->karana($today), PHP_EOL;

demoPace();
// Inauspicious windows are location-specific, which is why they take the same chart input.
echo 'Rahu Kala: ', $client->calculate()->rahuKala($today), PHP_EOL;

demoPace();
echo 'Sunrise:   ', $client->calculate()->sunriseTime($today), PHP_EOL;

demoPace();
echo 'Sunset:    ', $client->calculate()->sunsetTime($today), PHP_EOL;
