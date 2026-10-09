<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * A geographic location, in degrees.
 *
 * Coordinates are validated the moment the object is created, so a mistyped or swapped pair
 * fails immediately instead of producing a plausible but wrong chart.
 *
 * Longitude comes first, matching the Python, Node.js, Go and Java SDKs.
 */
final class GeoLocation implements \JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly float $longitude,
        public readonly float $latitude,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('vedastro: a location name is required');
        }

        if (is_nan($longitude) || is_infinite($longitude) || $longitude < -180.0 || $longitude > 180.0) {
            throw new \InvalidArgumentException(
                sprintf('vedastro: longitude must be between -180 and 180, got %s', $longitude)
            );
        }

        if (is_nan($latitude) || is_infinite($latitude) || $latitude < -90.0 || $latitude > 90.0) {
            throw new \InvalidArgumentException(
                sprintf('vedastro: latitude must be between -90 and 90, got %s', $latitude)
            );
        }
    }

    /** Convenience factory mirroring the other SDKs. */
    public static function of(string $name, float $longitude, float $latitude): self
    {
        return new self($name, $longitude, $latitude);
    }

    /**
     * The wire shape: `Name`, `Longitude`, `Latitude`, exactly as Python, Node.js, Go and Java
     * send it.
     *
     * @return array{Name: string, Longitude: float, Latitude: float}
     */
    public function toArray(): array
    {
        return [
            'Name' => $this->name,
            'Longitude' => $this->longitude,
            'Latitude' => $this->latitude,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return sprintf('%s (%s, %s)', $this->name, $this->longitude, $this->latitude);
    }
}
