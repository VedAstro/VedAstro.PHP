<?php

declare(strict_types=1);

namespace VedAstro;

use VedAstro\Generated\Ayanamsa;

/**
 * The VedAstro API client.
 *
 * The client owns its configuration and a reusable transport, and is stateless between calls, so
 * build one per application and share it:
 *
 * ```php
 * $client = new VedAstroClient(['apiKey' => getenv('VEDASTRO_API_KEY') ?: 'FreeAPIUser']);
 *
 * $birth = Time::of('14:30 25/10/1992 +05:30', GeoLocation::of('Mumbai', 72.8777, 19.0760));
 * $result = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);
 * echo Response::string($result, 'Name'); // Libra
 * ```
 *
 * - Every calculator blocks the calling process until the API responds, which suits PHP-FPM, a
 *   CLI worker, or a queue consumer.
 * - An empty API key uses the service's free tier. Never commit a paid key; read it from the
 *   environment.
 * - Failures throw {@see ApiException} (remote or malformed response) or
 *   {@see VedAstroException} (transport). Neither message contains the API key.
 * - The SDK never retries and never throttles; see the README for free-tier pacing.
 *
 * @phpstan-type ClientOptions array{
 *     apiKey?: string|null,
 *     baseUrl?: string,
 *     timeout?: int|null,
 *     transport?: Transport,
 *     defaultAyanamsa?: Ayanamsa|null,
 *     silent?: bool,
 * }
 */
final class VedAstroClient
{
    /** The public calculator service. */
    public const DEFAULT_BASE_URL = 'https://vedastro.zaishi.net/api/Calculate';

    /** The release version. */
    public const VERSION = '1.0.0';

    /** Prints the familiar one-line banner once per process, matching the other SDKs. */
    private static bool $bannerPrinted = false;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly Transport $transport;
    private readonly ?Ayanamsa $defaultAyanamsa;
    private readonly Calculators $calculators;

    /** A per-instance ayanamsa override, see {@see withAyanamsa()}. */
    private ?Ayanamsa $scopedAyanamsa = null;

    /**
     * @param array{
     *     apiKey?: string|null,
     *     baseUrl?: string,
     *     timeout?: int|null,
     *     transport?: Transport,
     *     defaultAyanamsa?: Ayanamsa|null,
     *     silent?: bool,
     * } $options Every setting is optional.
     */
    public function __construct(array $options = [])
    {
        $apiKey = $options['apiKey'] ?? null;
        $this->apiKey = $apiKey === null ? '' : trim($apiKey);

        $baseUrl = $options['baseUrl'] ?? self::DEFAULT_BASE_URL;
        if (trim($baseUrl) === '') {
            throw new \InvalidArgumentException('vedastro: base URL cannot be blank');
        }
        $this->baseUrl = rtrim(trim($baseUrl), '/');

        // No request deadline unless the caller sets one. A VedAstro calculation can legitimately
        // take minutes, and a library has no way to know what is safe for the caller's workload -
        // so imposing one would be brittle logic that can truncate a valid answer. Pass a
        // 'timeout' only when your own code has decided a request has run too long.
        $timeout = $options['timeout'] ?? null;
        if ($timeout !== null && (!is_int($timeout) || $timeout <= 0)) {
            throw new \InvalidArgumentException('vedastro: timeout must be a positive number of seconds');
        }

        $transport = $options['transport'] ?? self::defaultTransport($timeout);
        $this->transport = $transport;

        $this->defaultAyanamsa = $options['defaultAyanamsa'] ?? null;
        $this->calculators = new Calculators($this);

        $silent = $options['silent'] ?? false;
        if (!$silent && !self::$bannerPrinted) {
            self::$bannerPrinted = true;
            echo 'VedAstro : Easy To Use Advanced Astrology API', PHP_EOL;
        }
    }

    /** Creates a client with the given key and every other setting at its default. */
    public static function of(?string $apiKey = null): self
    {
        return new self(['apiKey' => $apiKey]);
    }

    /**
     * Creates a client from the `VEDASTRO_API_KEY` environment variable, falling back to the
     * free tier when it is unset.
     */
    public static function fromEnvironment(): self
    {
        $key = getenv('VEDASTRO_API_KEY');

        return new self(['apiKey' => $key === false ? null : $key]);
    }

    /** The 684 VedAstro calculators. */
    public function calculate(): Calculators
    {
        return $this->calculators;
    }

    /** The configured base URL. */
    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** The default ayanamsa, or `null` when the server chooses. */
    public function defaultAyanamsa(): ?Ayanamsa
    {
        return $this->defaultAyanamsa;
    }

    /** Reports whether a non-blank API key is configured. */
    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Runs the callable with a temporary ayanamsa, restoring the previous setting afterwards.
     *
     * ```php
     * $degrees = $client->withAyanamsa(Ayanamsa::RAMAN, fn () => $client->calculate()->ayanamsaDegree($birth));
     * ```
     *
     * The override is restored even when the callable throws.
     *
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     */
    public function withAyanamsa(?Ayanamsa $ayanamsa, callable $action): mixed
    {
        $previous = $this->scopedAyanamsa;
        $this->scopedAyanamsa = $ayanamsa;

        try {
            return $action();
        } finally {
            $this->scopedAyanamsa = $previous;
        }
    }

    /**
     * Calls a calculator by its API name.
     *
     * Useful for exploring the API, for an endpoint added on the server before the SDK is
     * regenerated, and for generic tooling. Prefer the typed methods on {@see calculate()}.
     *
     * @param string $endpoint The API calculator name, for example `"PlanetRasiD1Sign"`.
     * @param array<string, mixed> $parameters Request parameters; {@see Time}, {@see GeoLocation}
     *                                         and backed enums are encoded automatically.
     *
     * @return mixed The unwrapped payload.
     */
    public function call(string $endpoint, array $parameters = []): mixed
    {
        return $this->callWithResponse($endpoint, $parameters)->payload();
    }

    /**
     * Calls a calculator and returns the full envelope, for when you need the echoed input or
     * want to inspect the status yourself.
     */
    public function callWithResponse(string $endpoint, array $parameters = []): ApiResponse
    {
        if (trim($endpoint) === '') {
            throw new \InvalidArgumentException('vedastro: an endpoint name is required');
        }

        $body = $parameters;
        if ($this->apiKey !== '') {
            $body['APIKey'] = $this->apiKey;
        }

        $ayanamsa = $this->scopedAyanamsa ?? $this->defaultAyanamsa;
        if ($ayanamsa !== null) {
            $body['Ayanamsa'] = $ayanamsa->value;
        }

        try {
            // JSON_UNESCAPED_SLASHES keeps dates like 25/10/1992 readable and byte-identical to
            // the request bodies the Python, Node.js, Go and Java SDKs send.
            $encoded = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $failure) {
            throw new VedAstroException(
                'vedastro: request parameters cannot be encoded as JSON (' . $failure->getMessage() . ')',
                previous: $failure
            );
        }

        $response = $this->transport->post($this->baseUrl . '/' . $endpoint, $encoded);

        return $this->decode($endpoint, $response);
    }

    /**
     * The default transport: PHP's HTTP stream wrapper.
     *
     * The stream wrapper is chosen over cURL deliberately. It finds a certificate authority bundle
     * through `openssl` on any normal build, whereas cURL needs `curl.cainfo` configured in
     * php.ini and otherwise fails with "unable to get local issuer certificate" - a configuration
     * trap that is easy to miss until it reaches production. Pass
     * `['transport' => new CurlTransport()]` when cURL is set up correctly and you want it.
     *
     * @param int|null $timeoutSeconds Null applies no request deadline.
     */
    private static function defaultTransport(?int $timeoutSeconds): Transport
    {
        return new StreamTransport($timeoutSeconds);
    }

    private function decode(string $endpoint, TransportResponse $response): ApiResponse
    {
        $text = $response->body;
        $status = $response->statusCode;

        if ($status < 200 || $status >= 300) {
            throw $this->failure($endpoint, $status, $text === '' ? 'API HTTP request failed' : $this->snippet($text));
        }

        // Chart endpoints answer with SVG markup rather than a JSON envelope.
        if (str_contains($response->contentType, 'image/svg+xml')
            || str_starts_with(ltrim($text), '<svg')) {
            return new ApiResponse($endpoint, 'Pass', $text, []);
        }

        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $failure) {
            throw $this->failure(
                $endpoint,
                $status,
                'API returned invalid JSON: ' . $failure->getMessage()
            );
        }

        if (!is_array($decoded)) {
            throw $this->failure($endpoint, $status, 'API returned an unexpected response shape');
        }

        /** @var array<string, mixed> $decoded */
        $statusText = is_string($decoded['Status'] ?? null) ? $decoded['Status'] : '';

        if (strcasecmp($statusText, 'Fail') === 0) {
            $message = is_string($decoded['Payload'] ?? null) ? $decoded['Payload'] : 'API reported failure';
            throw $this->failure($endpoint, $status, $message);
        }

        if (!array_key_exists('Payload', $decoded) || $decoded['Payload'] === null) {
            throw $this->failure($endpoint, $status, 'API response is missing its Payload');
        }

        $payload = $decoded['Payload'];

        // Match the other SDKs: a single-property object is unwrapped to that property. The API
        // wraps every result as {"<MethodName>": <result>}, so this is the common case.
        if (is_array($payload) && count($payload) === 1) {
            $payload = reset($payload);
        }

        return new ApiResponse($endpoint, $statusText, $payload, $decoded);
    }

    /** Builds an exception whose message never echoes the API key. */
    private function failure(string $endpoint, int $status, string $message): ApiException
    {
        if ($this->apiKey !== '' && $message !== '') {
            $message = str_replace($this->apiKey, '[redacted]', $message);
        }

        return new ApiException($endpoint, $status, $message);
    }

    private function snippet(string $text): string
    {
        $collapsed = trim($text);

        return strlen($collapsed) <= 500 ? $collapsed : substr($collapsed, 0, 500) . '...';
    }
}
