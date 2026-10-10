<?php

declare(strict_types=1);

/**
 * The VedAstro PHP SDK test suite.
 *
 * Run it with:
 *
 * ```
 * php tests/run.php
 * ```
 *
 * No PHPUnit, no Composer, no network: the suite drives the real client through
 * {@see \VedAstro\Tests\FakeTransport}, and the one integration test talks to a local PHP
 * server started by this script.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/FakeTransport.php';
require __DIR__ . '/StreamWrapperFixture.php';

use VedAstro\ApiException;
use VedAstro\ApiResponse;
use VedAstro\Calculate;
use VedAstro\Calculators;
use VedAstro\GeoLocation;
use VedAstro\Generated\Ayanamsa;
use VedAstro\Generated\HouseName;
use VedAstro\Generated\PlanetName;
use VedAstro\Response;
use VedAstro\Tests\FakeTransport;
use VedAstro\Tests\StreamWrapperFixture;
use VedAstro\Time;
use VedAstro\TransportResponse;
use VedAstro\VedAstroClient;
use VedAstro\VedAstroException;

// ---------------------------------------------------------------- tiny test harness

final class Suite
{
    public int $passed = 0;
    public int $failed = 0;
    /** @var list<string> */
    public array $failures = [];
    private string $group = '';

    public function group(string $name): void
    {
        $this->group = $name;
        echo PHP_EOL, '── ', $name, PHP_EOL;
    }

    public function test(string $name, callable $body): void
    {
        try {
            $body($this);
            $this->passed++;
            echo '  ✓ ', $name, PHP_EOL;
        } catch (\Throwable $failure) {
            $this->failed++;
            $this->failures[] = $this->group . ' → ' . $name . ': ' . $failure->getMessage();
            echo '  ✗ ', $name, PHP_EOL, '      ', $failure->getMessage(), PHP_EOL;
        }
    }
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

function expectSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new \RuntimeException(sprintf(
            '%s (expected %s, got %s)',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function expectThrows(string $class, callable $body, string $message): \Throwable
{
    try {
        $body();
    } catch (\Throwable $failure) {
        if ($failure instanceof $class) {
            return $failure;
        }
        throw new \RuntimeException(sprintf('%s (threw %s instead)', $message, $failure::class));
    }

    throw new \RuntimeException($message . ' (nothing was thrown)');
}

function sampleBirth(): Time
{
    return Time::of('14:30 25/10/1992 +05:30', GeoLocation::of('Mumbai', 72.8777, 19.0760));
}

/** A client wired to a fake transport, with the banner silenced. */
function clientWith(FakeTransport $transport, array $options = []): VedAstroClient
{
    return new VedAstroClient(['transport' => $transport, 'silent' => true] + $options);
}

/** The envelope the live API returns for PlanetRasiD1Sign, captured from the service. */
const FIXTURE_PLANET_SIGN = <<<'JSON'
{
  "Status": "Pass",
  "Input": {
    "CalculatorName": "PlanetRasiD1Sign",
    "Type": "SingleCall",
    "Parameters": [
      { "Name": "planetName", "Type": "PlanetName", "Value": { "Name": "Sun" } },
      { "Name": "time", "Type": "Time", "Value": {
          "StdTime": "14:30 25/10/1992 +05:30",
          "Location": { "Name": "Mumbai", "Longitude": 72.8777, "Latitude": 19.076 } } }
    ]
  },
  "Payload": {
    "PlanetRasiD1Sign": {
      "Name": "Libra",
      "DegreesIn": { "DegreeMinuteSecond": "8° 26' 31", "TotalDegrees": "8.441944444444445" }
    }
  }
}
JSON;

/** The envelope for HouseSignName, whose payload is a plain string. */
const FIXTURE_HOUSE_SIGN = <<<'JSON'
{
  "Status": "Pass",
  "Payload": { "HouseSignName": "Aquarius" }
}
JSON;

// ---------------------------------------------------------------- tests

$suite = new Suite();
echo 'VedAstro PHP SDK — test suite', PHP_EOL, 'PHP ', PHP_VERSION, PHP_EOL;

$suite->group('Time and GeoLocation validation');

$suite->test('accepts the API time layout', function (): void {
    $location = GeoLocation::of('Mumbai', 72.8777, 19.0760);
    expectSame('14:30 25/10/1992 +05:30', Time::of('14:30 25/10/1992 +05:30', $location)->stdTime, 'string form');
    expectSame('00:00 01/01/2000 -05:00', Time::of('00:00 01/01/2000 -05:00', $location)->stdTime, 'negative offset');
    expectSame('06:30 25/10/1992 +00:00', Time::of('06:30 25/10/1992 +00:00', $location)->stdTime, 'UTC offset');
});

$suite->test('rejects the mistakes beginners make', function (): void {
    $location = GeoLocation::of('Mumbai', 72.8777, 19.0760);
    $cases = [
        '2:30 PM 25/10/1992 +05:30' => 'AM/PM is not supported',
        '14:30 1992-10-25 +05:30' => 'ISO date order',
        '14:30 25/10/1992' => 'missing offset',
        '14:30 25/10/1992 IST' => 'named zone instead of an offset',
        '4:30 25/10/1992 +05:30' => 'single-digit hour',
        '14:30 31/02/1992 +05:30' => '31 February does not exist',
        '25:00 25/10/1992 +05:30' => 'hour out of range',
        '14:30 25/13/1992 +05:30' => 'month out of range',
        '14:30 25/10/1992 +19:00' => 'offset out of range',
    ];
    foreach ($cases as $input => $why) {
        expectThrows(
            \InvalidArgumentException::class,
            static fn () => Time::of($input, $location),
            'expected a rejection for ' . $why
        );
    }
});

$suite->test('builds the same string from explicit fields', function (): void {
    $build = Time::ofFields(14, 30, 25, 10, 1992, '+05:30', GeoLocation::of('Mumbai', 72.8777, 19.0760));
    expectSame('14:30 25/10/1992 +05:30', $build->stdTime, 'field-built time should match the string form');
});

$suite->test('preserves the offset of a DateTimeInterface', function (): void {
    $location = GeoLocation::of('Mumbai', 72.8777, 19.0760);
    $ist = new \DateTimeImmutable('1992-10-25 14:30:00', new \DateTimeZone('Asia/Kolkata'));
    expectSame('14:30 25/10/1992 +05:30', Time::fromDateTime($ist, $location)->stdTime, 'Asia/Kolkata offset');

    $tokyo = new \DateTimeImmutable('1996-12-31 23:40:00', new \DateTimeZone('Asia/Tokyo'));
    expectSame('23:40 31/12/1996 +09:00', Time::fromDateTime($tokyo, $location)->stdTime, 'Asia/Tokyo offset');
});

$suite->test('validates location bounds and name', function (): void {
    expectThrows(\InvalidArgumentException::class, static fn () => GeoLocation::of('', 72.0, 19.0), 'blank name');
    expectThrows(\InvalidArgumentException::class, static fn () => GeoLocation::of('X', 181.0, 19.0), 'longitude too high');
    expectThrows(\InvalidArgumentException::class, static fn () => GeoLocation::of('X', 72.0, 91.0), 'latitude too high');
    expectThrows(\InvalidArgumentException::class, static fn () => GeoLocation::of('X', NAN, 19.0), 'NaN longitude');
    // The boundary values themselves are legal.
    expectSame(180.0, GeoLocation::of('X', 180.0, 90.0)->longitude, 'longitude 180 is valid');
    expectSame(-90.0, GeoLocation::of('X', -180.0, -90.0)->latitude, 'latitude -90 is valid');
});

$suite->group('Request encoding');

$suite->test('encodes Time and GeoLocation exactly as the other SDKs do', function (): void {
    // JSON_UNESCAPED_SLASHES matches what the client puts on the wire, and keeps the date
    // readable as 25/10/1992 rather than 25\/10\/1992.
    $encoded = json_encode(sampleBirth(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    expectSame(
        '{"StdTime":"14:30 25/10/1992 +05:30","Location":{"Name":"Mumbai","Longitude":72.8777,"Latitude":19.076}}',
        $encoded,
        'wire shape'
    );

    // The client must send the same bytes, so the other SDKs' logs match this one.
    $transport = new FakeTransport();
    clientWith($transport)->calculate()->planetRasiD1Sign(PlanetName::SUN, sampleBirth());
    expect(
        !str_contains($transport->lastBody()['time']['StdTime'] ?? '', '\\/'),
        'the request body must not escape slashes'
    );
});

$suite->test('encodes enums as their backed values', function (): void {
    expectSame('"Sun"', json_encode(PlanetName::SUN, JSON_THROW_ON_ERROR), 'PlanetName');
    expectSame('"House1"', json_encode(HouseName::H1, JSON_THROW_ON_ERROR), 'HouseName');
    expectSame('"LAHIRI"', json_encode(Ayanamsa::LAHIRI, JSON_THROW_ON_ERROR), 'Ayanamsa');
});

$suite->test('exposes every planet and the fan-out selector', function (): void {
    expectSame('Sun', PlanetName::SUN->value, 'Sun');
    expectSame('Ketu', PlanetName::KETU->value, 'Ketu');
    expectSame('All', PlanetName::All->value, 'All');
    expectSame('House12', HouseName::H12->value, 'the twelfth house');
    expectSame(23, count(PlanetName::cases()), 'every planet plus All');
    expectSame(47, count(Ayanamsa::cases()), 'all 47 ayanamsa systems');
});

$suite->group('Client behaviour (fake transport)');

$suite->test('posts to the endpoint with the key, planet and time on the wire', function (): void {
    $transport = new FakeTransport();
    $client = clientWith($transport, ['apiKey' => 'test-key']);

    $client->calculate()->planetRasiD1Sign(PlanetName::SUN, sampleBirth());

    expectSame(1, $transport->requestCount(), 'one request');
    expectSame(
        VedAstroClient::DEFAULT_BASE_URL . '/PlanetRasiD1Sign',
        $transport->lastUrl(),
        'endpoint URL'
    );

    $body = $transport->lastBody();
    expectSame('test-key', $body['APIKey'] ?? null, 'API key');
    expectSame('Sun', $body['planetName'] ?? null, 'planetName');
    expectSame('14:30 25/10/1992 +05:30', $body['time']['StdTime'] ?? null, 'time string');
    expectSame('Mumbai', $body['time']['Location']['Name'] ?? null, 'location name');
});

$suite->test('omits the API key entirely on the free tier', function (): void {
    $transport = new FakeTransport();
    $client = clientWith($transport);
    expect(!$client->hasApiKey(), 'a blank key should be treated as the free tier');

    $client->calculate()->houseSignName(HouseName::H1, sampleBirth());

    expect(!array_key_exists('APIKey', $transport->lastBody()), 'no APIKey should be sent');
});

$suite->test('sends the ayanamsa, and scopes a per-call override', function (): void {
    $transport = new FakeTransport();
    $client = clientWith($transport, ['defaultAyanamsa' => Ayanamsa::LAHIRI]);

    $client->calculate()->ayanamsaDegree(sampleBirth());
    expectSame('LAHIRI', $transport->lastBody()['Ayanamsa'] ?? null, 'client default');

    $client->withAyanamsa(Ayanamsa::RAMAN, static function () use ($client): void {
        $client->calculate()->ayanamsaDegree(sampleBirth());
    });
    expectSame('RAMAN', $transport->lastBody()['Ayanamsa'] ?? null, 'scoped override');

    // The override must not leak once the callable returns.
    $client->calculate()->ayanamsaDegree(sampleBirth());
    expectSame('LAHIRI', $transport->lastBody()['Ayanamsa'] ?? null, 'restored default');
});

$suite->test('restores the ayanamsa even when the callable throws', function (): void {
    $transport = new FakeTransport();
    $client = clientWith($transport, ['defaultAyanamsa' => Ayanamsa::LAHIRI]);

    try {
        $client->withAyanamsa(Ayanamsa::RAMAN, static function (): void {
            throw new \RuntimeException('boom');
        });
    } catch (\RuntimeException) {
        // expected
    }

    $client->calculate()->ayanamsaDegree(sampleBirth());
    expectSame('LAHIRI', $transport->lastBody()['Ayanamsa'] ?? null, 'default should be restored');
});

$suite->test('omits null optional arguments and sends the ones that are set', function (): void {
    $transport = new FakeTransport();
    $client = clientWith($transport);

    $client->calculate()->searchSourceText('Saturn');
    expect(!array_key_exists('topK', $transport->lastBody()), 'unset option should be omitted');
    expect(!array_key_exists('sourceName', $transport->lastBody()), 'unset option should be omitted');

    $options = new \VedAstro\Generated\SearchSourceTextOptions();
    $options->topK = 3;
    $options->sourceName = 'Brihat-Parashara-Hora-Shastra';
    $client->calculate()->searchSourceText('Saturn', $options);

    $body = $transport->lastBody();
    expectSame(3, $body['topK'] ?? null, 'topK should be sent');
    expectSame('Brihat-Parashara-Hora-Shastra', $body['sourceName'] ?? null, 'sourceName should be sent');
});

$suite->test('sends an explicit zero or false rather than dropping it', function (): void {
    $transport = new FakeTransport();
    $client = clientWith($transport);

    $options = new \VedAstro\Generated\AshtakavargaTransitPredictionOptions();
    $options->useRawBindus = false;
    $client->calculate()->ashtakavargaTransitPrediction(PlanetName::SUN, 1, 27, sampleBirth(), $options);

    $body = $transport->lastBody();
    expect(array_key_exists('useRawBindus', $body), 'false must still be sent');
    expectSame(false, $body['useRawBindus'], 'the value must be false');
});

$suite->group('Response decoding');

$suite->test('unwraps the single-key payload the API always sends', function (): void {
    $transport = new FakeTransport(new TransportResponse(200, FIXTURE_PLANET_SIGN, 'application/json'));
    $result = clientWith($transport)->calculate()->planetRasiD1Sign(PlanetName::SUN, sampleBirth());

    expect(is_array($result), 'the payload should unwrap to an array, not the envelope');
    expectSame('Libra', Response::string($result, 'Name'), 'the sign name');
    expectSame(8.441944444444445, Response::float($result, ['DegreesIn', 'TotalDegrees']), 'a nested number');
    expectSame("8° 26' 31", Response::string($result, ['DegreesIn', 'DegreeMinuteSecond']), 'a nested string');
});

$suite->test('unwraps a scalar payload to a plain string', function (): void {
    $transport = new FakeTransport(new TransportResponse(200, FIXTURE_HOUSE_SIGN, 'application/json'));
    $result = clientWith($transport)->calculate()->houseSignName(HouseName::H1, sampleBirth());

    expectSame('Aquarius', $result, 'HouseSignName unwraps to a bare string');
});

$suite->test('keeps a multi-property payload as an array', function (): void {
    $transport = new FakeTransport(new TransportResponse(
        200,
        '{"Status":"Pass","Payload":{"Name":"Libra","DegreesIn":{"TotalDegrees":"8.44"}}}',
        'application/json'
    ));
    $result = clientWith($transport)->calculate()->planetRasiD1Sign(PlanetName::SUN, sampleBirth());

    expectSame('Libra', Response::string($result, 'Name'), 'name');
    expect(Response::has($result, 'DegreesIn'), 'the nested object should survive');
});

$suite->test('returns SVG markup as a plain string', function (): void {
    $transport = new FakeTransport(new TransportResponse(200, '<svg viewBox="0 0 10 10"></svg>', 'image/svg+xml'));
    $result = clientWith($transport)->calculate()->southIndianChart(sampleBirth());

    expect(is_string($result), 'an SVG response should be a string');
    expect(str_starts_with($result, '<svg'), 'the markup should be returned verbatim');
});

$suite->test('exposes the full envelope when asked', function (): void {
    $transport = new FakeTransport(new TransportResponse(200, FIXTURE_PLANET_SIGN, 'application/json'));
    $response = clientWith($transport)->callWithResponse('PlanetRasiD1Sign', []);

    expect($response instanceof ApiResponse, 'callWithResponse returns an ApiResponse');
    expect($response->isSuccess(), 'the status should read as success');
    expectSame('Pass', $response->status(), 'the status text');
    expectSame('PlanetRasiD1Sign', $response->endpoint, 'the endpoint');
    expectSame('Libra', Response::string($response->payload(), 'Name'), 'the payload');
    expect(isset($response->envelope()['Input']), 'the echoed Input block should be available');
});

$suite->group('Errors');

$suite->test('raises ApiException when the API reports a failure', function (): void {
    $transport = new FakeTransport(new TransportResponse(
        200,
        '{"Status":"Fail","Payload":"Free tier rate limit exceeded (5 calls/minute)."}',
        'application/json'
    ));

    $failure = expectThrows(
        ApiException::class,
        static fn () => clientWith($transport)->calculate()->planetRasiD1Sign(PlanetName::SUN, sampleBirth()),
        'a Fail status should raise ApiException'
    );

    expectSame('PlanetRasiD1Sign', $failure->endpoint(), 'the endpoint is carried');
    expectSame(200, $failure->statusCode(), 'the status code is carried');
    expect(str_contains($failure->getMessage(), 'rate limit'), 'the API message is surfaced');
});

$suite->test('raises ApiException for a non-success status and for malformed JSON', function (): void {
    $bad = new FakeTransport(new TransportResponse(500, 'upstream exploded', 'text/plain'));
    expectThrows(ApiException::class, static fn () => clientWith($bad)->call('X', []), 'HTTP 500');

    $garbage = new FakeTransport(new TransportResponse(200, 'not json at all', 'application/json'));
    expectThrows(ApiException::class, static fn () => clientWith($garbage)->call('X', []), 'invalid JSON');

    $noPayload = new FakeTransport(new TransportResponse(200, '{"Status":"Pass"}', 'application/json'));
    expectThrows(ApiException::class, static fn () => clientWith($noPayload)->call('X', []), 'missing Payload');
});

$suite->test('never echoes the API key in an error message', function (): void {
    $transport = new FakeTransport(new TransportResponse(
        200,
        '{"Status":"Fail","Payload":"bad key secret-key-123"}',
        'application/json'
    ));

    $failure = expectThrows(
        ApiException::class,
        static fn () => clientWith($transport, ['apiKey' => 'secret-key-123'])->call('X', []),
        'the API failure should raise'
    );

    expect(!str_contains($failure->getMessage(), 'secret-key-123'), 'the key must not appear');
    expect(str_contains($failure->getMessage(), '[redacted]'), 'the key should be redacted');
});

$suite->test('surfaces a transport failure as VedAstroException', function (): void {
    $transport = new FakeTransport();
    $transport->willFail(new VedAstroException('vedastro: HTTP request failed'));

    expectThrows(
        VedAstroException::class,
        static fn () => clientWith($transport)->call('X', []),
        'a transport failure should propagate'
    );
});

$suite->test('rejects a blank endpoint and unusable options', function (): void {
    $transport = new FakeTransport();
    expectThrows(
        \InvalidArgumentException::class,
        static fn () => clientWith($transport)->call('   ', []),
        'a blank endpoint should be rejected'
    );
    expectThrows(
        \InvalidArgumentException::class,
        static fn () => new VedAstroClient(['baseUrl' => ' ']),
        'a blank base URL should be rejected'
    );
    expectThrows(
        \InvalidArgumentException::class,
        static fn () => new VedAstroClient(['timeout' => 0]),
        'a zero timeout should be rejected'
    );
    expectThrows(
        \InvalidArgumentException::class,
        static fn () => new VedAstroClient(['timeout' => -5]),
        'a negative timeout should be rejected'
    );

    // The default is no deadline at all, so a long calculation is never cut short. Check the
    // transport the client actually built, rather than trusting the constructor.
    $default = new VedAstroClient(['silent' => true]);
    $property = new \ReflectionProperty(\VedAstro\StreamTransport::class, 'timeoutSeconds');
    $defaultTransport = (new \ReflectionProperty(VedAstroClient::class, 'transport'))->getValue($default);
    expect($defaultTransport instanceof \VedAstro\StreamTransport, 'the default transport is the stream wrapper');
    expectSame(null, $property->getValue($defaultTransport), 'no deadline is imposed by default');

    // An explicit timeout is honoured, and reaches the transport.
    $limited = new VedAstroClient(['silent' => true, 'timeout' => 42]);
    $limitedTransport = (new \ReflectionProperty(VedAstroClient::class, 'transport'))->getValue($limited);
    expectSame(42, $property->getValue($limitedTransport), 'an explicit timeout reaches the transport');
});

$suite->group('Configuration and facade');

$suite->test('normalises a trailing slash on the base URL', function (): void {
    $transport = new FakeTransport();
    clientWith($transport, ['baseUrl' => 'https://example.test/api/'])
        ->calculate()->planetRasiD1Sign(PlanetName::SUN, sampleBirth());

    expectSame('https://example.test/api/PlanetRasiD1Sign', $transport->lastUrl(), 'no double slash');
});

$suite->test('calls an endpoint that has no generated method', function (): void {
    $transport = new FakeTransport(new TransportResponse(
        200,
        '{"Status":"Pass","Payload":{"result":42}}',
        'application/json'
    ));

    $result = clientWith($transport)->call('SomethingNewOnTheServer', ['time' => sampleBirth()]);

    expectSame(42, $result, 'the payload should come back');
    expectSame(
        VedAstroClient::DEFAULT_BASE_URL . '/SomethingNewOnTheServer',
        $transport->lastUrl(),
        'the named endpoint'
    );
});

$suite->test('the Calculate facade reaches the whole API', function (): void {
    $transport = new FakeTransport(new TransportResponse(200, FIXTURE_PLANET_SIGN, 'application/json'));
    Calculate::setClient(clientWith($transport));

    $result = Calculate::planetRasiD1Sign(PlanetName::SUN, sampleBirth());

    expectSame('Libra', Calculate::name($result), 'the facade reads the payload');
    expect(Calculate::of() instanceof Calculators, 'of() returns the calculators');
    expectSame(1, $transport->requestCount(), 'the facade used the injected client');
});

$suite->group('Response helpers');

$suite->test('read nested values without throwing on a wrong shape', function (): void {
    $payload = ['Name' => 'Libra', 'DegreesIn' => ['TotalDegrees' => '8.44'], 'List' => [1, 2, 3]];

    expectSame('Libra', Response::string($payload, 'Name'), 'top-level string');
    expectSame(8.44, Response::float($payload, ['DegreesIn', 'TotalDegrees']), 'nested numeric string');
    expectSame(3, Response::count($payload, 'List'), 'list length');
    expect(Response::has($payload, 'Name'), 'has() finds a key');
    expect(!Response::has($payload, 'Missing'), 'has() reports a missing key');

    // Wrong shapes return the default rather than raising.
    expectSame('fallback', Response::string($payload, 'Missing', 'fallback'), 'string default');
    expectSame(-1, Response::int($payload, ['DegreesIn', 'TotalDegrees', 'nope'], -1), 'int default');
    expect(!Response::bool($payload, 'Missing'), 'bool default');
    expectSame([], Response::list($payload, 'Missing'), 'list default');
    expectSame(0.0, Response::float('not an array', 'Name'), 'float default on a scalar payload');
});

// ---------------------------------------------------------------- integration

$suite->group('Integration (live HTTP against a local server)');

$suite->test('performs a real HTTP round trip through the stream transport', function (): void {
    // A stream wrapper serves the responses in-process. The client, the transport, the JSON
    // encoding and the response parsing are all the real ones - only the socket is absent, which
    // keeps the test hermetic and leaves no subprocess to keep the runner alive.
    StreamWrapperFixture::register();
    $baseUrl = StreamWrapperFixture::SCHEME . '://api/Calculate';

    try {
        // The stream transport is pinned rather than auto-selected, because the default picks
        // cURL when that extension is loaded - and cURL cannot speak a custom PHP stream scheme.
        $client = new VedAstroClient([
            'apiKey' => 'test-key',
            'baseUrl' => $baseUrl,
            'silent' => true,
            'transport' => new \VedAstro\StreamTransport(),
        ]);

        // A realistic envelope, exactly as the live service sends it.
        StreamWrapperFixture::$body = StreamWrapperFixture::envelope('PlanetRasiD1Sign', [
            'Name' => 'Libra',
            'DegreesIn' => [
                'DegreeMinuteSecond' => "8° 26' 31",
                'TotalDegrees' => '8.441944444444445',
            ],
        ]);

        $sign = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, sampleBirth());
        expectSame('Libra', Response::string($sign, 'Name'), 'the sign came back over HTTP');
        expectSame(
            8.441944444444445,
            Response::float($sign, ['DegreesIn', 'TotalDegrees']),
            'the nested number arrived'
        );

        // Prove what actually travelled, by inspecting the recorded request.
        expectSame('test-key', StreamWrapperFixture::$lastBody['APIKey'] ?? null, 'the key was sent');
        expectSame('Sun', StreamWrapperFixture::$lastBody['planetName'] ?? null, 'the planet was sent');
        expectSame(
            '14:30 25/10/1992 +05:30',
            StreamWrapperFixture::$lastBody['time']['StdTime'] ?? null,
            'the Time object serialised on the wire'
        );
        expectSame(
            'Mumbai',
            StreamWrapperFixture::$lastBody['time']['Location']['Name'] ?? null,
            'the nested Location serialised on the wire'
        );
        expect(
            str_contains(implode("\n", StreamWrapperFixture::$lastHeaders), 'application/json'),
            'the request advertised JSON'
        );

        // A scalar payload unwraps to a bare string.
        StreamWrapperFixture::$body = StreamWrapperFixture::envelope('HouseSignName', 'Aquarius');
        $house = $client->calculate()->houseSignName(HouseName::H1, sampleBirth());
        expectSame('Aquarius', $house, 'a scalar payload unwraps over HTTP');

        // A chart endpoint answers with SVG markup rather than an envelope.
        StreamWrapperFixture::$contentType = 'image/svg+xml';
        StreamWrapperFixture::$body = '<svg viewBox="0 0 10 10"></svg>';
        $svg = $client->calculate()->southIndianChart(sampleBirth());
        expect(is_string($svg) && str_starts_with($svg, '<svg'), 'an SVG response arrives as markup');

        // A failure status must raise, and carry the endpoint.
        StreamWrapperFixture::$contentType = 'application/json';
        StreamWrapperFixture::$body = '{"Status":"Fail","Payload":"Free tier rate limit exceeded (5 calls/minute)."}';
        $failure = expectThrows(
            ApiException::class,
            static fn () => $client->call('PlanetRasiD1Sign', []),
            'a Fail status over HTTP should raise'
        );
        expectSame('PlanetRasiD1Sign', $failure->endpoint(), 'the endpoint is carried');

        // A non-2xx status must raise too.
        StreamWrapperFixture::$statusCode = 500;
        StreamWrapperFixture::$body = 'upstream exploded';
        expectThrows(
            ApiException::class,
            static fn () => $client->call('PlanetRasiD1Sign', []),
            'HTTP 500 should raise'
        );
    } finally {
        StreamWrapperFixture::unregister();
        StreamWrapperFixture::$statusCode = 200;
        StreamWrapperFixture::$contentType = 'application/json';
    }
});

// ---------------------------------------------------------------- summary

echo PHP_EOL, str_repeat('─', 60), PHP_EOL;
printf('passed: %d   failed: %d%s', $suite->passed, $suite->failed, PHP_EOL);

if ($suite->failed > 0) {
    echo PHP_EOL, 'failures:', PHP_EOL;
    foreach ($suite->failures as $failure) {
        echo '  • ', $failure, PHP_EOL;
    }
    exit(1);
}

echo 'All tests passed.', PHP_EOL;
exit(0);
