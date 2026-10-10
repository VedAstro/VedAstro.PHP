<?php

declare(strict_types=1);

/**
 * Live verification against the real VedAstro API.
 *
 * Run it with `php tests/live_check.php`. Unlike the test suite, this script needs network access,
 * and it spends real API calls, so it is not part of CI.
 *
 * The free tier allows five requests per minute, so the script paces itself. Set
 * VEDASTRO_API_KEY to use a paid key.
 */

require __DIR__ . '/../src/autoload.php';

use VedAstro\Generated\Ayanamsa;
use VedAstro\Generated\HouseName;
use VedAstro\Generated\PlanetName;
use VedAstro\GeoLocation;
use VedAstro\Response;
use VedAstro\Time;
use VedAstro\VedAstroClient;

$key = getenv('VEDASTRO_API_KEY');
$freeTier = $key === false || trim($key) === '';

$failures = [];

/** Runs a check, pacing the free tier, and reports the outcome. */
function check(string $label, callable $action, bool $freeTier, array &$failures): void
{
    try {
        $result = $action();
        printf("  ✓ %-14s %s\n", $label, $result);
    } catch (\Throwable $failure) {
        $failures[] = $label . ': ' . $failure->getMessage();
        printf("  ✗ %-14s %s\n", $label, $failure->getMessage());
    }

    if ($freeTier) {
        sleep(13); // the free tier allows five requests per minute
    }
}

// Both transports are exercised, so a TLS configuration problem in either one shows up here
// rather than in production. VEDASTRO_TRANSPORT=stream|curl picks just one.
$requested = getenv('VEDASTRO_TRANSPORT');
$transports = match ($requested) {
    'curl' => ['cURL' => extension_loaded('curl') ? new VedAstro\CurlTransport() : null],
    'stream' => ['stream wrapper' => new VedAstro\StreamTransport()],
    default => [
        'stream wrapper' => new VedAstro\StreamTransport(),
        'cURL' => extension_loaded('curl') ? new VedAstro\CurlTransport() : null,
    ],
};

echo 'VedAstro PHP SDK — live check against ', VedAstroClient::DEFAULT_BASE_URL, PHP_EOL, PHP_EOL;

foreach ($transports as $transportName => $transport) {
    if ($transport === null) {
        printf("transport: %s — skipped (extension not loaded)\n\n", $transportName);
        continue;
    }

    // cURL cannot verify TLS without a CA bundle, which many PHP builds leave unconfigured.
    // Report that rather than a wall of certificate errors.
    if ($transport instanceof VedAstro\CurlTransport && $transport->caBundle() === null) {
        printf(
            "transport: %s — skipped (no CA bundle; set curl.cainfo in php.ini)\n\n",
            $transportName
        );
        continue;
    }

    printf("transport: %s\n\n", $transportName);

    $client = new VedAstroClient([
        'apiKey' => $freeTier ? 'FreeAPIUser' : $key,
        'silent' => true,
        'transport' => $transport,
    ]);
    $birth = Time::of('14:30 25/10/1992 +05:30', GeoLocation::of('Mumbai', 72.8777, 19.0760));

    check('Sun sign', static function () use ($client, $birth): string {
        $sun = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);

    return Response::string($sun, 'Name')
        . ' at ' . Response::string($sun, ['DegreesIn', 'DegreeMinuteSecond']);
}, $freeTier, $failures);

check('Ascendant', static function () use ($client, $birth): string {
    return (string) $client->calculate()->houseSignName(HouseName::H1, $birth);
}, $freeTier, $failures);

check('Ayanamsa', static function () use ($client, $birth): string {
    $degree = $client->withAyanamsa(
        Ayanamsa::LAHIRI,
        static fn () => $client->calculate()->ayanamsaDegree($birth)
    );

    return Response::string($degree, 'DegreeMinuteSecond');
}, $freeTier, $failures);

check('Panchanga', static function () use ($client, $birth): string {
    $tithi = $client->calculate()->lunarDay($birth);

    return Response::string($tithi, 'Name') . ' (' . Response::string($tithi, 'Paksha') . ')';
}, $freeTier, $failures);

check('Numerology', static function () use ($client, $birth): string {
    return 'birth number ' . $client->calculate()->birthNumber($birth);
}, $freeTier, $failures);

check('RAG search', static function () use ($client): string {
    $passages = $client->calculate()->searchSourceText('effects of Saturn in the 7th house');

    return Response::count($passages) . ' passage(s)';
    }, $freeTier, $failures);

    echo PHP_EOL;
}

if ($failures !== []) {
    echo 'live check failures:', PHP_EOL;
    foreach ($failures as $failure) {
        echo '  • ', $failure, PHP_EOL;
    }
    exit(1);
}

echo 'All live checks passed.', PHP_EOL;
exit(0);
