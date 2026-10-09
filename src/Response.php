<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * A helper for reading decoded API payloads.
 *
 * Every calculator returns the unwrapped payload, which is a plain PHP `array`, `string`,
 * `float`, `int`, `bool`, or `null`. PHP has no generics, so these accessors keep scalar
 * juggling out of your code and never throw on an unexpected shape: they return the supplied
 * default instead.
 *
 * ```php
 * $result = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);
 * echo Response::string($result, 'Name');        // "Libra"
 * ```
 */
final class Response
{
    private function __construct()
    {
    }

    /**
     * Reads a field from an object payload.
     *
     * @param mixed $payload A decoded payload.
     * @param array<int, string>|string $path A field name, or a path of nested field names.
     */
    public static function get(mixed $payload, array|string $path, mixed $default = null): mixed
    {
        $keys = is_array($path) ? $path : [$path];
        $current = $payload;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /** Reads a field as a string, or returns the default when it is absent or not scalar. */
    public static function string(mixed $payload, array|string $path, string $default = ''): string
    {
        $value = self::get($payload, $path);

        if (is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    /** Reads a field as a float, or returns the default when it is not numeric. */
    public static function float(mixed $payload, array|string $path, float $default = 0.0): float
    {
        $value = self::get($payload, $path);

        return is_numeric($value) ? (float) $value : $default;
    }

    /** Reads a field as an int, or returns the default when it is not numeric. */
    public static function int(mixed $payload, array|string $path, int $default = 0): int
    {
        $value = self::get($payload, $path);

        return is_numeric($value) ? (int) $value : $default;
    }

    /** Reads a field as a bool, or returns the default when it is absent. */
    public static function bool(mixed $payload, array|string $path, bool $default = false): bool
    {
        $value = self::get($payload, $path);

        return is_bool($value) ? $value : (is_scalar($value) ? (bool) $value : $default);
    }

    /**
     * Reads a field as a list.
     *
     * @return array<int|string, mixed>
     */
    public static function list(mixed $payload, array|string $path = []): array
    {
        $value = $path === [] ? $payload : self::get($payload, $path);

        return is_array($value) ? $value : [];
    }

    /** Reports whether a nested path exists in a payload. */
    public static function has(mixed $payload, array|string $path): bool
    {
        $sentinel = new \stdClass();

        return self::get($payload, $path, $sentinel) !== $sentinel;
    }

    /** Counts the entries at a path, or 0 when it is not a list. */
    public static function count(mixed $payload, array|string $path = []): int
    {
        return count(self::list($payload, $path));
    }
}
