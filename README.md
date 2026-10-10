<h1 align="center">🪐 VedAstro for PHP</h1>

<p align="center">
  <em>The most comprehensive Vedic astrology library for PHP — 684 calculations, one method call.</em>
</p>

<p align="center">
  <a href="https://packagist.org/packages/vedastro/vedastro"><img src="https://img.shields.io/badge/composer-vedastro%2Fvedastro-8892BF?style=flat-square&logo=composer&logoColor=white" alt="Composer package"/></a>
  <a href="https://github.com/VedAstro/VedAstro.PHP/blob/main/composer.json"><img src="https://img.shields.io/badge/php-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP version"/></a>
  <a href="https://github.com/VedAstro/VedAstro.PHP/blob/main/LICENSE"><img src="https://img.shields.io/github/license/VedAstro/VedAstro.PHP?style=flat-square&color=fda085" alt="License"/></a>
  <a href="https://github.com/VedAstro/VedAstro.PHP/stargazers"><img src="https://img.shields.io/github/stars/VedAstro/VedAstro.PHP?style=flat-square&color=f6d365" alt="Stars"/></a>
</p>

---

### 🎯 Built for Real Apps

Perfect for:
- 📱 **Horoscope sites and apps** - Get 200+ life predictions instantly
- 💑 **Marriage matching services** - 16-factor Kuta compatibility analysis
- 📅 **Daily panchanga pages** - Tithi, Nakshatra, Yoga, Karana
- 🔮 **AI astrology chatbots** - Natural language birth chart queries, with cited sources
- 📊 **Astrological research** - Batch process thousands of charts
- 🌟 **Numerology calculators** - Chaldean system with life aspect scores

**Zero runtime dependencies.** The library uses only PHP itself — `json_encode`/`json_decode` for
JSON and PHP's HTTP stream wrapper for transport — so it drops into Laravel, Symfony, Slim or plain
PHP-FPM without a dependency tree to resolve.

The default transport is the **stream wrapper**, deliberately: it finds a certificate authority
bundle through `openssl` on any normal build. cURL needs `curl.cainfo` configured in php.ini and
otherwise fails with "unable to get local issuer certificate" — a trap that is easy to miss until
it reaches production. Pass `['transport' => new CurlTransport()]` when cURL is configured
correctly and you want it.

---

## 🏃 Quick Start (3 minutes to your first calculation)

### Installation (10 seconds)

```bash
composer require vedastro/vedastro
```

Requires **PHP 8.1 or newer** with the `json` extension (bundled by default). There is nothing
else to install: no native libraries, no ephemeris files, no system packages.

### Your First Calculation (20 seconds)

```php
<?php

require 'vendor/autoload.php';

use VedAstro\Calculate;
use VedAstro\Generated\PlanetName;
use VedAstro\GeoLocation;
use VedAstro\Response;
use VedAstro\Time;

// Free tier key. For a paid key, set VEDASTRO_API_KEY and use
// Calculate::setApiKey(getenv('VEDASTRO_API_KEY')).
Calculate::setApiKey('FreeAPIUser');

// Time format: "HH:MM DD/MM/YYYY +TZ:TZ".
// GeoLocation takes the name, then LONGITUDE, then latitude.
$birth = Time::of('14:30 25/10/1992 +05:30', GeoLocation::of('Mumbai', 72.8777, 19.0760));

// One call returns the Sun's sign.
$result = Calculate::planetRasiD1Sign(PlanetName::SUN, $birth);

echo 'Sun Sign: ', Response::string($result, 'Name'), PHP_EOL; // Libra
```

**That's it! You just made your first Vedic astrology calculation.** 🎉

> **Results are dynamic.** A calculator returns `mixed` holding the unwrapped API payload: an
> `array`, a `string`, a `float`, an `int`, a `bool`, or `null`. PHP has no generics, so read
> fields through the `Response` helpers rather than casting blindly. See
> [Decoded results](#-decoded-results).

---

## 📚 Beginner-Friendly Examples

Every snippet is complete. They use a client from the [Quick Start](#-quick-start-3-minutes-to-your-first-calculation),
or the static `Calculate` facade, and this helper for reading a field out of a payload:

```php
use VedAstro\Response;

// Reads a Name-style field from an object payload, with a fallback.
$name = Response::string($payload, 'Name', 'unknown');
```

### Example 1: Get Birth Chart Basics

**Use case:** display the Sun, Moon and Ascendant signs

```php
use VedAstro\Generated\HouseName;
use VedAstro\Generated\PlanetName;
use VedAstro\GeoLocation;
use VedAstro\Response;
use VedAstro\Time;
use VedAstro\VedAstroClient;

$client = new VedAstroClient(['apiKey' => getenv('VEDASTRO_API_KEY') ?: 'FreeAPIUser']);

$birth = Time::of('14:30 25/10/1992 +05:30', GeoLocation::of('Mumbai', 72.8777, 19.0760));

$sun = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);
$moon = $client->calculate()->planetRasiD1Sign(PlanetName::MOON, $birth);
// House 1 is the ascendant, and HouseSignName unwraps to a plain string.
$rising = $client->calculate()->houseSignName(HouseName::H1, $birth);

echo 'Sun:    ', Response::string($sun, 'Name'), PHP_EOL;   // e.g. Libra
echo 'Moon:   ', Response::string($moon, 'Name'), PHP_EOL;  // e.g. Scorpio
echo 'Rising: ', $rising, PHP_EOL;                          // e.g. Capricorn
```

👉 **See full example:** [`examples/birth_chart_basics.php`](examples/birth_chart_basics.php)

---

### Example 2: Marriage Compatibility

**Use case:** check whether two people are compatible for marriage

```php
$person1 = Time::of('23:40 31/12/1996 +09:00', GeoLocation::of('Tokyo', 139.83, 35.65));
$person2 = Time::of('14:30 15/06/1997 -05:00', GeoLocation::of('New York', -74.006, 40.7128));

$report = $client->calculate()->matchReport($person1, $person2);

printf('Compatibility: %.0f/100%s', Response::float($report, 'KutaScore'), PHP_EOL);
echo 'Status: ', Response::string($report, ['Summary', 'ScoreSummary']), PHP_EOL;

// Each factor carries a Nature of Good, Bad or Neutral.
foreach (Response::list($report, 'PredictionList') as $kuta) {
    echo '  • ', Response::string($kuta, 'Name'), ': ', Response::string($kuta, 'Nature'), PHP_EOL;
}
```

**Output** (values depend on the two charts):

```
Compatibility: 65/100
Status: Good match - Near perfect match, overall happiness
  • Graha Maitram: Good
  • Rajju: Good
  • Nadi Kuta: Good
  • Vasya Kuta: Bad
  • Dina Kuta: Good
```

👉 **See full example:** [`examples/marriage_compatibility.php`](examples/marriage_compatibility.php)

---

### Example 3: Current Planetary Positions

**Use case:** get today's positions for any location

```php
$mumbai = GeoLocation::of('Mumbai', 72.8777, 19.0760);
// Time::now() builds the moment in the given zone, preserving its offset.
$now = Time::now($mumbai, new DateTimeZone('Asia/Kolkata'));

$planets = [PlanetName::SUN, PlanetName::MOON, PlanetName::MARS, PlanetName::MERCURY,
    PlanetName::JUPITER, PlanetName::VENUS, PlanetName::SATURN, PlanetName::RAHU, PlanetName::KETU];

foreach ($planets as $planet) {
    // One call returns everything about the planet, which is cheaper than two.
    $data = $client->calculate()->allPlanetData($planet, $now);
    printf(
        "  %-8s %-12s %s%s",
        $planet->value,
        Response::string($data, ['PlanetRasiD1Sign', 'Name'], '-'),
        Response::string($data, ['PlanetConstellation', 'Name'], '-'),
        PHP_EOL
    );
}
```

**Output:**
```
  Sun      Libra        Chitta - 3
  Moon     Libra        Vishhaka - 2
  ...
```

> Free-tier pacing: this loop makes one call per planet. See [Pacing requests](#-pacing-requests-and-rate-limits).

👉 **See full example:** [`examples/current_planets.php`](examples/current_planets.php)

---

### Example 4: Daily Panchanga

**Use case:** today's Tithi, Nakshatra, Yoga and Karana

```php
$today = Time::now($mumbai, new DateTimeZone('Asia/Kolkata'));

// LunarDay and NithyaYoga return objects; the others unwrap to strings.
$tithi = $client->calculate()->lunarDay($today);
echo 'Tithi:     ', Response::string($tithi, 'Name'), ' (', Response::string($tithi, 'Paksha'), ')', PHP_EOL;

echo 'Nakshatra: ', $client->calculate()->moonConstellation($today), PHP_EOL;

$yoga = $client->calculate()->nithyaYoga($today);
echo 'Yoga:      ', Response::string($yoga, 'Name'), PHP_EOL;

echo 'Karana:    ', $client->calculate()->karana($today), PHP_EOL;
```

**Output:**
```
Tithi:     Amavasya (Krishna)
Nakshatra: Chitta - 3
Yoga:      Vishkambha
Karana:    Chatushpada
```

**Note:** shapes vary per calculator, so check `api_manifest.json` rather than assuming. Panchanga
is reckoned from local sunrise, so the location matters.

👉 **See full example:** [`examples/daily_panchanga.php`](examples/daily_panchanga.php)

---

### Example 5: Numerology Calculator

**Use case:** Chaldean numerology for a name and a birth date

```php
$name = 'John Doe';

echo 'Birth number:   ', $client->calculate()->birthNumber($birth), PHP_EOL;
echo 'Destiny number: ', $client->calculate()->destinyNumber($birth), PHP_EOL;

$prediction = $client->calculate()->nameNumberPrediction($name);
echo 'Number:         ', Response::get($prediction, 'Number'), PHP_EOL;
echo 'Root number:    ', Response::get($prediction, 'RootNumber'), PHP_EOL;
echo 'Ruling planet:  ', Response::string($prediction, 'Planet'), PHP_EOL;

// Prediction is HTML formatted, so strip the tags for a terminal.
echo 'Interpretation: ', substr(strip_tags(Response::string($prediction, 'Prediction')), 0, 400), PHP_EOL;
```

**Output:**
```
Birth number:   7
Destiny number: 2
Number:         34
Root number:    7
Ruling planet:  Ketu
Interpretation: This number has the potential to be seen as lucky, ...
```

👉 **See full example:** [`examples/numerology.php`](examples/numerology.php)

---

### Example 6: Choosing an Ayanamsa

**Use case:** compare the same chart under different ayanamsa systems

```php
use VedAstro\Generated\Ayanamsa;

foreach ([Ayanamsa::LAHIRI, Ayanamsa::RAMAN, Ayanamsa::KRISHNAMURTI] as $ayanamsa) {
    // withAyanamsa scopes the choice to this call and restores the previous setting after,
    // so nothing leaks into later requests.
    $degree = $client->withAyanamsa(
        $ayanamsa,
        static fn () => $client->calculate()->ayanamsaDegree($birth)
    );
    printf("  %-16s %s%s", $ayanamsa->value, Response::string($degree, 'DegreeMinuteSecond'), PHP_EOL);
}
```

**Output:**
```
  LAHIRI           23° 45' 39
  RAMAN            22° 18' 2
  KRISHNAMURTI     23° 39' 52
```

👉 **See full example:** [`examples/custom_ayanamsa.php`](examples/custom_ayanamsa.php)

---

### Example 7: Semantic Search of Classical Texts (RAG)

**Use case:** ask a plain-English question and get the most relevant passages from works such as
Brihat Parashara Hora Shastra and Phaladeepika — no exact keywords needed

```php
use VedAstro\Generated\SearchSourceTextOptions;

// Which classical texts are searchable?
foreach (Response::list($client->calculate()->getAvailableSourceTexts()) as $title) {
    echo '  - ', $title, PHP_EOL;
}

// A natural-language question, not a keyword search.
$passages = $client->calculate()->searchSourceText('effects of Saturn in the 7th house');

foreach (Response::list($passages) as $passage) {
    $score = Response::float($passage, 'score', 1.0);
    // A lower score is a closer match, so invert it into a relevance percentage.
    printf(
        "%s p.%s (%.0f%%)%s",
        Response::string($passage, 'sourceName'),
        Response::get($passage, 'pageNumber', '?'),
        (1 - $score) * 100,
        PHP_EOL
    );
    echo '   ', Response::string($passage, 'text'), PHP_EOL;
}

// Narrow to one book and tune the knobs.
$options = new SearchSourceTextOptions();
$options->topK = 3;
$options->sourceName = 'Hindu-Predictive-Astrology';
$options->contextSize = 800;
$focused = $client->calculate()->searchSourceText('results of Jupiter aspecting the Moon', $options);
```

> 🤖 This is the same retrieval step that powers VedAstro's RAG/AI features — feed the returned
> passages into an LLM prompt to build a **cited** astrology chatbot.

👉 **See full example:** [`examples/rag_vedic_books.php`](examples/rag_vedic_books.php)

---

## 🎓 Step-by-Step Tutorials

### Tutorial 1: Understanding Time Format

**The most common beginner issue: the time string.**

**Format:** `"HH:MM DD/MM/YYYY +HH:MM"` — 24-hour clock, DD/MM/YYYY order, explicit numeric UTC offset.

| Location | Example | Offset |
|----------|---------|--------|
| India | `"14:30 25/10/1992 +05:30"` | IST = UTC+5:30 |
| USA (East) | `"09:30 25/10/1992 -05:00"` | EST = UTC-5:00 |
| USA (West) | `"06:30 25/10/1992 -08:00"` | PST = UTC-8:00 |
| Japan | `"23:30 25/10/1992 +09:00"` | JST = UTC+9:00 |
| UK | `"14:30 25/10/1992 +00:00"` | GMT = UTC+0:00 |

**Three ways to build a `Time`:**

```php
// 1. The API string, validated immediately.
$birth = Time::of('14:30 25/10/1992 +05:30', $mumbai);

// 2. Separate calendar fields, zero-padded for you.
$same = Time::ofFields(14, 30, 25, 10, 1992, '+05:30', $mumbai);

// 3. From a DateTimeInterface, preserving its offset.
$fromDate = Time::fromDateTime(new DateTimeImmutable('1992-10-25 14:30:00', new DateTimeZone('Asia/Kolkata')), $mumbai);

// 4. The current moment, in a specific zone.
$now = Time::now($mumbai, new DateTimeZone('Asia/Kolkata'));
```

**Common mistakes, all of which throw `InvalidArgumentException` at the call site:**

```php
Time::of('2:30 PM 25/10/1992 +05:30', $mumbai);  // AM/PM is not supported
Time::of('14:30 1992-10-25 +05:30', $mumbai);    // ISO date order
Time::of('14:30 25/10/1992', $mumbai);           // missing offset
Time::of('14:30 25/10/1992 IST', $mumbai);       // named zone, not an offset
Time::of('14:30 31/02/1992 +05:30', $mumbai);    // 31 February does not exist
```

> `GeoLocation::of($name, $longitude, $latitude)` takes **longitude first**. A swapped pair is the
> second most common bug, and it silently produces a plausible but wrong chart, so coordinates are
> range-checked when the location is built.

---

### Tutorial 2: Choosing the Right Ayanamsa

**What is an ayanamsa?**

The ayanamsa is the gap between the tropical (Western) and sidereal (Vedic) zodiacs. Different
systems shift a planet by up to about 3°, which is enough to change its sign near a boundary.

**47 systems are available.** Common choices:

| Constant | When to use |
|----------|-------------|
| `Ayanamsa::LAHIRI` | Indian government standard, most widely used — **the server default** |
| `Ayanamsa::RAMAN` | Popular in South India |
| `Ayanamsa::KRISHNAMURTI` | KP (Krishnamurti Paddhati) system |
| `Ayanamsa::FAGAN_BRADLEY` | Western sidereal astrology |
| `Ayanamsa::YUKTESHWAR` | Sri Yukteswar's calculation |

**Two ways to set it:**

```php
// 1. Per client: the default for every request this client sends.
$client = new VedAstroClient([
    'apiKey' => $apiKey,
    'defaultAyanamsa' => Ayanamsa::LAHIRI,
]);

// 2. Per call, restored automatically afterwards.
$degree = $client->withAyanamsa(Ayanamsa::RAMAN, fn () => $client->calculate()->ayanamsaDegree($birth));

// 3. Not at all: the server applies its own default, which measures as Lahiri.
```

A scoped override wins over the client default, and it is restored even if the closure throws.

**Recommendation:**
- 🇮🇳 **Indian astrology** → `Ayanamsa::LAHIRI`
- 🌏 **Unsure** → omit it; the server default measures as Lahiri
- 📐 **KP system** → `Ayanamsa::KRISHNAMURTI`
- 🌍 **Western sidereal** → `Ayanamsa::FAGAN_BRADLEY`

👉 **See full example:** [`examples/custom_ayanamsa.php`](examples/custom_ayanamsa.php)

---

### Tutorial 3: Interpreting Match Reports

**Understanding the Kuta score:**

| Score range | Compatibility | Recommendation |
|-------------|--------------|----------------|
| 33-36 points | Excellent | Highly compatible |
| 25-32 points | Good | Compatible, proceed with confidence |
| 18-24 points | Average | Requires careful consideration |
| Below 18 | Poor | Not recommended without other factors |

**The 16 Kutas explained:**

1. **Graha Maitram (5 pts)** — mental compatibility, happiness
2. **Gana (6 pts)** — temperament match (Deva/Manushya/Rakshasa)
3. **Yoni (4 pts)** — sexual compatibility
4. **Nadi (8 pts)** — health and progeny (traditionally weighted most heavily)
5. **Varna (1 pt)** — spiritual/ego compatibility
6. …and 11 more factors

**Reading the report:**

```php
$report = $client->calculate()->matchReport($person1, $person2);

echo Response::float($report, 'KutaScore'), PHP_EOL;                        // normalised to 100
echo Response::string($report, ['Summary', 'ScoreSummary']), PHP_EOL;

foreach (Response::list($report, 'PredictionList') as $kuta) {
    echo Response::string($kuta, 'Name'), ': ', Response::string($kuta, 'Nature'), PHP_EOL;
    echo '  Info: ', Response::string($kuta, 'Info'), PHP_EOL;
}
```

---

## 🔧 Common Use Cases & Demo Files

Twelve runnable programs live in [`examples/`](examples). Run any of them directly:

```bash
php examples/quick_start.php
php examples/daily_panchanga.php
```

| Demo | Use case | Skill level |
|------|----------|-------------|
| [`quick_start.php`](examples/quick_start.php) | The simplest possible program | Beginner |
| [`birth_chart_basics.php`](examples/birth_chart_basics.php) | Sun, Moon, ascendant, birth constellation | Beginner |
| [`current_planets.php`](examples/current_planets.php) | Today's positions for a location | Beginner |
| [`daily_panchanga.php`](examples/daily_panchanga.php) | Tithi, Nakshatra, Yoga, Karana, sunrise | Beginner |
| [`marriage_compatibility.php`](examples/marriage_compatibility.php) | Full 16-factor match report | Beginner |
| [`numerology.php`](examples/numerology.php) | Birth, destiny and name numbers | Beginner |
| [`divisional_charts.php`](examples/divisional_charts.php) | D1, D9, D10, D12 signs | Intermediate |
| [`custom_ayanamsa.php`](examples/custom_ayanamsa.php) | Switching between the 47 systems | Intermediate |
| [`rag_vedic_books.php`](examples/rag_vedic_books.php) | Semantic search of classical texts | Intermediate |
| [`error_handling.php`](examples/error_handling.php) | Validation, API failures, retries | Intermediate |
| [`server_usage.php`](examples/server_usage.php) | Owning one client in a web framework | Intermediate |
| [`batch_processing.php`](examples/batch_processing.php) | Many charts, paced and cached | Advanced |

The demos read `VEDASTRO_API_KEY` and fall back to the free tier, pacing themselves 13 seconds
apart when no key is set. They also honour `VEDASTRO_BASE_URL`, which is how the test suite runs
them against a local fixture server.

---

## 🔢 Decoded results

The API's `Payload` is decoded with `json_decode(..., true)`, so:

| JSON | PHP |
|------|-----|
| Object | `array` (associative) |
| Array | `array` (list) |
| Number | `int` or `float` |
| String | `string` |
| Boolean | `bool` |
| null | `null` |
| SVG response | `string` |

As in the Python, Node.js, Go and Java SDKs, a payload object with exactly one property is
unwrapped to that property's value. Because the API wraps every result as
`{"<MethodName>": ...}`, that is the common case — which is why `planetRasiD1Sign` may return an
array while `houseSignName` returns a bare string.

Zero, `false`, empty strings, empty arrays and `null` are all valid results, so never treat a
falsy value as "missing". Use `Response::has()` when you need to know the difference.

```php
$result = $client->calculate()->planetNirayanaLongitude(PlanetName::SUN, $birth);

$longitude = Response::float($result);          // a bare number payload
$rounded = Response::int($result);
$known = Response::has($result, 'Name');
```

The accessors never throw on an unexpected shape: they return the fallback you supply.

---

## ⚙️ Optional arguments

Thirty calculators have optional parameters. Each one gets a small options class whose properties
are nullable: **a null property is omitted, so the server applies its own default**, and an
explicit value — including `0`, `false` or `''` — is sent.

```php
use VedAstro\Generated\SearchSourceTextOptions;

// Omit optional arguments entirely and take the server defaults.
$all = $client->calculate()->searchSourceText('Saturn');

// Or pass an options object.
$options = new SearchSourceTextOptions();
$options->topK = 3;
$options->sourceName = 'Brihat-Parashara-Hora-Shastra';
$focused = $client->calculate()->searchSourceText('Saturn', $options);
```

Defaults are documented on each property and listed in
[`src/Generated/api_manifest.json`](src/Generated/api_manifest.json).

---

## 🔑 Keys, clients, and configuration

Read your key from the environment at start-up:

```php
$client = new VedAstroClient([
    'apiKey' => getenv('VEDASTRO_API_KEY') ?: 'FreeAPIUser',
    'timeout' => 30,
    'defaultAyanamsa' => Ayanamsa::LAHIRI,
]);
```

An **empty key** uses the service's free tier. Never commit a paid key; read it from the
environment or your framework's secret store.

| Option | Effect |
|--------|--------|
| `apiKey` | Your key, or blank for the free tier |
| `timeout` | Per-request seconds, including the body (default 120) |
| `defaultAyanamsa` | Ayanamsa sent with this client's requests |
| `baseUrl` | Point at a different Calculate service or a test server |
| `transport` | Supply your own `Transport`, e.g. for stubs or a proxy |
| `silent` | Suppress the one-line start-up banner |

Invalid configuration is rejected as soon as it is set, so a bad value fails at start-up rather
than on a live request.

---

## 🧵 Using it in a server

A `VedAstroClient` is immutable after construction and stateless between calls, so build **one**
per process and inject it. PHP is shared-nothing per request, so in PHP-FPM "once per process"
means once per worker, not once per request.

**Laravel** — bind it as a singleton:

```php
// app/Providers/VedAstroServiceProvider.php
public function register(): void
{
    $this->app->singleton(VedAstroClient::class, fn () => new VedAstroClient([
        'apiKey' => config('services.vedastro.key'),
        'timeout' => 30,
        'silent' => true,          // the banner would corrupt a response body
    ]));
}
```

**Symfony** — declare it as a service:

```yaml
# config/services.yaml
services:
    VedAstro\VedAstroClient:
        arguments:
            $options:
                apiKey: '%env(VEDASTRO_API_KEY)%'
                timeout: 30
                silent: true
```

**A handler**, whatever the framework:

```php
public function sunSign(Request $request, VedAstroClient $client): JsonResponse
{
    $birth = Time::of(
        $request->string('time'),
        GeoLocation::of('request', $request->float('longitude'), $request->float('latitude'))
    );

    try {
        $result = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);

        return response()->json(['sign' => Response::string($result, 'Name')]);
    } catch (ApiException $remote) {
        report($remote);           // the key is already redacted

        return response()->json(['error' => 'upstream'], 502);
    }
}
```

Good practice on a server:

- **One client per process, injected.** Do not construct one per request.
- **Keep the timeout below the web server's own**, so the SDK fails first with a clear error.
- **Silence the banner** in anything that writes a response body.
- **Cache by chart input.** The same birth details recur, and a cache hit costs nothing.
- **Do not retry blindly.** Only idempotent reads are safe, and only on transport errors.
- **Never log the key.** The SDK already redacts it from every message it raises.

### ⚠️ Fit the timeout inside PHP's own limits

This is the one PHP-specific trap worth understanding, because the failure it causes is not
catchable.

Three deadlines apply to a request, and **the shortest one wins**:

| Deadline | Typical value | How it fails |
|----------|---------------|--------------|
| `VedAstroClient` `timeout` | 120s by default | `VedAstroException`, catchable |
| `default_socket_timeout` (ini) | 60s | stream read fails, catchable |
| `max_execution_time` (ini) | 30s under PHP-FPM, unlimited on CLI | **fatal error, not catchable** |

The SDK's 120-second default is longer than the 30 seconds PHP-FPM usually allows, so a slow
request is killed by PHP before the client can raise its own exception — the caller gets a fatal
error instead of a `VedAstroException` it can handle. Set the client timeout comfortably below
`max_execution_time`:

```php
// PHP-FPM default is 30s, so fail at 25s and stay catchable.
$client = new VedAstroClient([
    'apiKey' => getenv('VEDASTRO_API_KEY'),
    'timeout' => 25,
    'silent' => true,
]);
```

For a long-running CLI worker or queue consumer, where `max_execution_time` is 0 (unlimited),
raise the client timeout instead — `['timeout' => 120]` — and pace calls yourself.

Two more PHP specifics:

- **The client is per-request, not per-application.** PHP's shared-nothing model tears down the
  process after each request, so "create it once" means once per request unless you run a
  persistent runtime (Swoole, RoadRunner, FrankenPHP). Under those, hold the client in the worker
  and reuse it across requests.
- **Nothing is retried for you, and PHP has no threads.** To raise throughput, add workers or use
  `curl_multi` behind a custom `Transport`; the rate limit is counted per key across every worker,
  so pace centrally (a token bucket in Redis) rather than per process.

👉 **See full example:** [`examples/server_usage.php`](examples/server_usage.php)

---

## 🧯 Errors

```php
use VedAstro\ApiException;
use VedAstro\VedAstroException;

try {
    $result = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);
} catch (ApiException $remote) {
    // The API answered but reported a failure: bad input, bad key, or a rate limit.
    error_log(sprintf(
        'vedastro %s HTTP %d: %s',
        $remote->endpoint(),
        $remote->statusCode(),
        $remote->getMessage()
    ));
} catch (VedAstroException $transport) {
    // No usable response: DNS, TLS, connect timeout, or a malformed body.
    error_log('vedastro transport: ' . $transport->getMessage());
}
```

- `ApiException` extends `VedAstroException`, which extends `RuntimeException`, so catching the
  base type handles everything the SDK throws.
- `ApiException` carries `endpoint()` and `statusCode()`.
- The configured API key is replaced with `[redacted]` in every message, so it cannot leak into logs.
- **The SDK never retries and never throttles**, because only the caller knows whether a second
  request is safe.

👉 **See full example:** [`examples/error_handling.php`](examples/error_handling.php)

---

## ⏱️ Pacing requests and rate limits

The free tier allows **five requests per minute**, counted per API key across every request and
process. Space calls at least **13 seconds** apart.

```php
// A minimal pacer for a single process.
final class Pacer
{
    private float $nextAllowedAt = 0.0;

    public function __construct(private readonly float $gapSeconds)
    {
    }

    public function acquire(): void
    {
        $now = microtime(true);
        $waitFor = $this->nextAllowedAt - $now;
        $this->nextAllowedAt = max($now, $this->nextAllowedAt) + $this->gapSeconds;

        if ($waitFor > 0) {
            usleep((int) ($waitFor * 1_000_000));
        }
    }
}

$pacer = new Pacer(60 / 5); // five calls per minute
$pacer->acquire();
$result = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);
```

Because the limit is per key, it is shared across every PHP worker and every queue consumer. For
anything beyond one process, pace centrally — a token bucket in Redis is the usual answer.

> Note: `sleep()` takes whole seconds, so use `usleep()` for an exact 13-second gap. PHP's `sleep()`
> also returns early when the process receives a signal.

---

## 📖 What Can You Calculate? (684 Methods)

**Full API reference:** [vedastro.org/API.html](https://vedastro.org/API.html) ·
[`src/Generated/api_manifest.json`](src/Generated/api_manifest.json) lists every method, its
parameters and its defaults.

Every calculator is a method on `Calculators`, reachable as `$client->calculate()-><name>(...)`,
or statically as `Calculate::<name>(...)`.

### Core chart analysis (225 methods)
`planetRasiD1Sign`, `planetNirayanaLongitude`, `planetConstellation`, `planetsInSign`,
`planetsInConjunction`, `isPlanetRetrograde`, `isPlanetExalted`, `isPlanetDebilitated`,
`houseSignName`, `houseRasiSign`, `lordOfHouse`, `allPlanetData`, `allHouseData`,
`allZodiacSignData`, +211 more

### Divisional charts — vargas (102 methods)
`allHouseNavamshaSign` (D9), `allHouseDrekkanaSign` (D3), `allHouseChaturthamsaSign` (D4),
`planetShashtyamshaD60Sign` (D60), `planetDivisionalLongitude`, D1–D60 vargas

### Panchanga, muhurta & time (65 methods)
`lunarDay`, `tithiNumber`, `nakshatraPada`, `nithyaYoga`, `karana`, `rahuKala`, `gulikaKala`,
`durmuhurta`, `sunriseTime`, `sunsetTime`, `horaTable`, `ayanamsaDegree`,
`planetEphemerisLongitude`, +51 more

### Strength, dignity & Shadbala (62 methods)
`planetShadbalaPinda`, `planetStrength`, `houseStrength`, `planetIshtaScore`, `planetKashtaScore`,
`allPlanetOrderedByStrength`, `pickOutStrongestPlanet`, `planetDignity`, `isPlanetVargottama`,
+53 more

### Charts, aspects & events (55 methods)
`northIndianChart`, `southIndianChart`, `skyChart`, `planetAspectDegree`, `planetsAspectingPlanet`,
`isPlanetAspectedByPlanet`, `eventsAtTime`, `eventsAtRange`, `eventStartTime`,
`horoscopePredictions`, `swissEphemeris`, +44 more

### Prashna, chakra & Pancha Pakshi (28 methods)
`chapter5PrashnaMargaPredictions` … `chapter30PrashnaMargaPredictions`, `sarvatobhadraChakra`,
`kotaChakra`, `sudarsanaChakra`, `birthYamaPanchaPakshi`, `calculateAshtamangalaNumberFromShells`,
+few more

### Ashtakvarga (20 methods)
`sarvashtakavargaChart`, `bhinnashtakavargaChart`, `planetAshtakvargaBindu`, `gocharaKakshas`,
`ashtakavargaLongevity`, `prastaraAshtakavarga`, `sodyaAshtakavarga`, +13 more

### Dasa — planetary periods (20 methods)
`dasaAtRange`, `dasaAtTime`, `dasaForNow`, `dasaForLife`, `moolaDasa`, `narayanaDasa`,
`kalachakraDasa`, `tithiAshtottariDasa`, +12 more

### Earthquake research (20 methods)
`calculateEarthquakeRiskScore`, `isEarthquakeNearEclipse`, `isEarthquakeJupiterSaturnConjunction`,
`isEarthquakePlanetsClusteredInNarrowArc`, +16 more

### Longevity & life events (18 methods)
`detailedAshtakavargaLongevity`, `marriageByJupiter`, `childBirthByJupiter1`, `nativeDeathBySaturn`,
`hasBalarishtaExceptions`, `marakaPlanetList`, +12 more

### Upagraha & special points (15 methods)
`gulikaLongitude`, `maandiLongitude`, `dhumaLongitude`, `upaketuLongitude`, `kaalaLongitude`,
`fortunaPoint`, `destinyPoint`, `isUpagraha`, +7 more

### Transit & timing — gochara (13 methods)
`planetSignTransit`, `transitHouseFromLagna`, `transitHouseFromMoon`, `isGocharaOccurring`,
`getConstellationTransitStartTime`, `isPlanetRetrograde`, +7 more

### Yogas, doshas & kartari (12 methods)
`kalaSarpaYoga`, `jHoraYogaList`, `isPlanetInGandanta`, `kujaDosaScore`, `classifyForKartari`,
`shubKartariPlanets`, `paapaKartariPlanets`, +5 more

### Jaimini & Tajika (8 methods)
`jaiminiRasiDrishti`, `jaiminiRasiStrength`, `tajakaVarshaphala`, `tajakaYogaList`,
`trueSiderealSolarReturn`, `arudhaLagnaSign`, +2 more

### AI, ML & text search (7 methods)
`findBirthTimeByMachineLearning`, `findBirthTimeByMachineLearningTopK`, `findBirthTimeByAnimal`,
`searchSourceText` (RAG over classical texts), `getAvailableSourceTexts`,
`horoscopePredictionsForLargeAstrologyModelTrainingData`, +1 more

### Numerology (6 methods)
`birthNumber`, `destinyNumber`, `nameNumber`, `nameNumberPrediction`, `rootNumberFriendship`,
`mainActivity`

### Compatibility & matching (4 methods)
`matchReport`, `tarabala`, `chandrabala`, `yoniKutaAnimal`

### Health & nature scores (4 methods)
`predictMedicalHealthConditions`, `houseNatureScore`, `planetNatureScore`,
`getActiveNccBodyRulesAtTime`

---

## 🚀 Why VedAstro? The Simplest & Most Affordable Vedic Astrology API

### ✨ Unbeatable Value

| What you get | VedAstro | Competitors |
|--------------|----------|-------------|
| **Monthly cost** | **$1/month** | $50–$200/month |
| **Free tier** | ✅ 5 req/min | ❌ None or very limited |
| **Calculations** | **684 methods** | 50–200 methods |
| **Ayanamsa systems** | **47 systems** | 3–10 systems |
| **Setup complexity** | **Zero setup** | Complex (DLLs, ephemeris files) |
| **Commercial use** | ✅ Both tiers | ❌ Enterprise only |

**The bottom line:** get 10x more features at 1/50th the price. No credit card needed to start.

### 💰 Pricing That Makes Sense

| Tier | Price | Rate limit | Best for |
|------|-------|------------|----------|
| **Free** | $0/month | 5 req/min | Learning, testing, personal projects |
| **Premium** | **$1/month** | Higher (service-set) | Production apps, commercial use |

**Indian developers:** ₹79/month or ₹758/year (₹63/month, most popular)

> **All 684 calculations are included in both tiers.** The only difference is throughput. Premium is
> quoted as **200 calls/minute** by the API's own rate-limit error; confirm current numbers at
> [vedastro.org/API.html](https://vedastro.org/API.html).

---

## 🏗️ How It Works (Architecture)

```
Your PHP Code
      v
vedastro/vedastro (this library, PHP only)
      v
REST API (vedastro.zaishi.net)
      v
VedAstro Engine (Azure Cloud)
      v
Swiss Ephemeris (NASA JPL data)
```

The library is a thin, typed client: it builds the request body, POSTs it, unwraps the standard
`{ Status, Payload }` envelope, and returns the inner payload. The calculations themselves run on
VedAstro's servers.

**Why this design?**
- ✅ Zero third-party dependencies — JSON, enums and transport are all built in
- ✅ Instant updates (684 calculations, always the latest, with no client release needed)
- ✅ No extensions required beyond `json`; transport is the built-in HTTP stream wrapper, with an
  opt-in cURL transport when you want it
- ✅ Cross-platform (any PHP 8.1+ on Linux, macOS or Windows)

---

## 🛠️ Development and generation

`src/Generated/` is produced by **StaticTableGenerator Task 13** in the
[main VedAstro repository](https://github.com/VedAstro/VedAstro). **Do not edit generated files** —
`CalculatesApi.php` starts with a "DO NOT EDIT" header.

Generated: `CalculatesApi.php` (the 684-method trait), `Options.php` (the optional-argument
holders), one enum per API enum, `PlanetName.php`, and `api_manifest.json`.

```bash
# Lint every file
php tests/lint.php

# Run the test suite (no network, no PHPUnit, no Composer)
php tests/run.php

# Verify against the real API (needs network; spends free-tier calls)
php tests/live_check.php
```

Task 13 writes to `../VedAstro.PHP-PUBLIC/src/Generated`. Set `VEDASTRO_PHP_OUT` to override it.

The trait is mixed into the hand-written `VedAstro\Calculators` class, which supplies
`$this->vedAstro`. That split is deliberate: the public class and its constructor stay stable
while the 684-method surface is regenerated.

---

## 🤝 Contributing

Contributions are welcome — issues and PRs at [VedAstro.PHP](https://github.com/VedAstro/VedAstro.PHP).

> **Note:** everything under `src/Generated` is generated. To change it, change the server metadata
> and re-run Task 13, then run `php tests/run.php` here.

---

## 📄 License

MIT License — free for commercial and personal use.

---

## 🙏 Support the Project

VedAstro is non-profit and user-funded. If it saves you time and money:

- ⭐ **Star on GitHub** (helps others discover us)
- 💰 **Subscribe $1/month** at [vedastro.org/API.html](https://vedastro.org/API.html)
- 🎁 **Donate** at [vedastro.org/Donate](https://vedastro.org/Donate)
- 📢 **Share** with other developers

Every subscription helps keep VedAstro free and open-source! 🙏

---

## 📚 Additional Resources

- 📖 **Full API Docs**: [vedastro.org/API.html](https://vedastro.org/API.html)
- 🚀 **Quick Start**: [QUICKSTART.md](QUICKSTART.md)
- ❓ **FAQ**: [FAQ.md](FAQ.md)
- 🐍 **Python SDK**: [VedAstro.Python](https://github.com/VedAstro/VedAstro.Python)
- 🟨 **Node.js SDK**: [VedAstro.NodeJS](https://github.com/VedAstro/VedAstro.NodeJS)
- 🔵 **Go SDK**: [VedAstro.Go](https://github.com/VedAstro/VedAstro.Go)
- ☕ **Java SDK**: [VedAstro.Java](https://github.com/VedAstro/VedAstro.Java)
- 💬 **Telegram**: [t.me/vedastro_org](https://t.me/vedastro_org)
- 🐛 **Issues**: [GitHub Issues](https://github.com/VedAstro/VedAstro.PHP/issues)
- 🌐 **Website**: [vedastro.org](https://vedastro.org)

---

<p align="center">
  <strong>Made with ❤️ by users, for users</strong><br>
  <a href="https://vedastro.org">Website</a> •
  <a href="https://vedastro.org/API.html">API Docs</a> •
  <a href="https://github.com/VedAstro/VedAstro">GitHub</a> •
  <a href="https://t.me/vedastro_org">Telegram</a> •
  <a href="https://vedastro.org/Donate">Donate</a>
</p>

<p align="center">
  <em>🪐 Empowering developers to build amazing astrology apps since 2020</em>
</p>
