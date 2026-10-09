<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * Static convenience facade over a shared {@see VedAstroClient}.
 *
 * Use this for scripts, one-off tasks, and small tools where threading a client through every
 * call is noise. The underlying client is built once from `VEDASTRO_API_KEY` and reused, so a
 * long-running worker can use it too:
 *
 * ```php
 * Calculate::setApiKey('FreeAPIUser');
 * $sun = Calculate::planetRasiD1Sign(PlanetName::SUN, $birth);
 * echo Calculate::name($sun); // Libra
 * ```
 *
 * For full control — timeouts, a custom transport, a default ayanamsa — inject a
 * {@see VedAstroClient} instead. Call {@see setApiKey()} before the first calculation; changing
 * it later replaces the shared client.
 */
final class Calculate
{
    private static ?VedAstroClient $client = null;

    private function __construct()
    {
    }

    /**
     * The shared client, created on first use from `VEDASTRO_API_KEY`, which falls back to the
     * free tier when the variable is unset.
     */
    public static function client(): VedAstroClient
    {
        return self::$client ??= VedAstroClient::fromEnvironment();
    }

    /** Replaces the shared client with one that uses the given key. */
    public static function setApiKey(?string $apiKey): void
    {
        self::$client = VedAstroClient::of($apiKey);
    }

    /** Replaces the shared client entirely, for example with a fully configured one. */
    public static function setClient(VedAstroClient $client): void
    {
        self::$client = $client;
    }

    /** The shared client's calculators. */
    public static function calculators(): Calculators
    {
        return self::client()->calculate();
    }

    /** Alias for {@see calculators()}, for symmetry with the other SDKs' facades. */
    public static function of(): Calculators
    {
        return self::calculators();
    }

    /** Calls a calculator by its API name on the shared client. */
    public static function call(string $endpoint, array $parameters = []): mixed
    {
        return self::client()->call($endpoint, $parameters);
    }

    /** Runs the callable with a temporary ayanamsa on the shared client. */
    public static function withAyanamsa(?Generated\Ayanamsa $ayanamsa, callable $action): mixed
    {
        return self::client()->withAyanamsa($ayanamsa, $action);
    }

    /**
     * Forwards any unknown static call to the shared client's calculators, so the whole API is
     * reachable as `Calculate::planetRasiD1Sign(...)`.
     *
     * @param array<int, mixed> $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::calculators()->{$method}(...$arguments);
    }

    // ------------------------------------------------------------------ helpers
    // Reading a field out of a decoded payload needs no client, so these are plain statics.

    /** Reads a `Name`-style field from a payload, or returns the fallback. */
    public static function name(mixed $payload, string $key = 'Name', ?string $fallback = null): ?string
    {
        $value = Response::get($payload, $key);

        if (is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : $fallback;
    }

    /** Reads a list payload, or returns an empty array when the value is not a list. */
    public static function list(mixed $payload): array
    {
        return Response::list($payload);
    }
}
