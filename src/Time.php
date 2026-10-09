<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * A moment in time at a location, in the format the VedAstro API expects:
 * `"HH:MM DD/MM/YYYY +HH:MM"`.
 *
 * The offset is mandatory and must be a fixed UTC offset such as `+05:30`. Timezone names like
 * `IST` are not accepted, because a chart must not depend on the server's timezone database.
 *
 * Both components are validated on construction, so an invalid time fails at the call site with
 * a clear message rather than as a confusing API error.
 *
 * ```php
 * // From an explicit string (validated immediately)
 * $birth = Time::of('14:30 25/10/1992 +05:30', GeoLocation::of('Mumbai', 72.8777, 19.0760));
 *
 * // From a DateTimeInterface, keeping its own offset
 * $now = Time::fromDateTime(new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')), $mumbai);
 * ```
 */
final class Time implements \JsonSerializable
{
    /** The API's time layout, using a numeric offset. */
    public const WIRE_FORMAT = 'HH:MM DD/MM/YYYY +HH:MM';

    private const PATTERN = '/^(\d{2}):(\d{2}) (\d{2})\/(\d{2})\/(\d{4}) ([+-]\d{2}:\d{2})$/';

    public readonly string $stdTime;
    public readonly GeoLocation $location;

    public function __construct(string $stdTime, GeoLocation $location)
    {
        self::requireApiFormat($stdTime);

        $this->stdTime = $stdTime;
        $this->location = $location;
    }

    /**
     * Creates a time from an API time string.
     *
     * @param string $stdTime For example `"14:30 25/10/1992 +05:30"`.
     *
     * @throws \InvalidArgumentException When the string is not a valid API time.
     */
    public static function of(string $stdTime, GeoLocation $location): self
    {
        return new self($stdTime, $location);
    }

    /**
     * Creates a time from separate calendar fields, zero-padding each part to the API layout.
     */
    public static function ofFields(
        int $hour,
        int $minute,
        int $day,
        int $month,
        int $year,
        string $offset,
        GeoLocation $location,
    ): self {
        return new self(
            sprintf('%02d:%02d %02d/%02d/%04d %s', $hour, $minute, $day, $month, $year, $offset),
            $location
        );
    }

    /**
     * Creates a time from any `DateTimeInterface`, preserving its own calendar fields and UTC
     * offset.
     *
     * Pass a value already in the target zone — for example one built with
     * `new DateTimeZone('Asia/Kolkata')` — rather than relabelling a local time, otherwise the
     * calculated chart will be offset.
     */
    public static function fromDateTime(\DateTimeInterface $moment, GeoLocation $location): self
    {
        return new self($moment->format('H:i d/m/Y P'), $location);
    }

    /** Creates a time for the current instant, in the given zone. */
    public static function now(GeoLocation $location, ?\DateTimeZone $zone = null): self
    {
        $moment = $zone === null ? new \DateTimeImmutable('now') : new \DateTimeImmutable('now', $zone);

        return self::fromDateTime($moment, $location);
    }

    /**
     * The wire shape: `StdTime` plus the nested `Location`, exactly as the other SDKs send it.
     *
     * @return array{StdTime: string, Location: array{Name: string, Longitude: float, Latitude: float}}
     */
    public function toArray(): array
    {
        return [
            'StdTime' => $this->stdTime,
            'Location' => $this->location->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->stdTime . ' at ' . $this->location;
    }

    /** Validates the full API layout, rejecting impossible dates and offsets. */
    private static function requireApiFormat(string $value): void
    {
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'vedastro: time must use %s (24-hour clock, DD/MM/YYYY order), got \'%s\'',
                self::WIRE_FORMAT,
                $value
            ));
        }

        [, $hour, $minute, $day, $month, $year, $offset] = $matches;

        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            throw new \InvalidArgumentException(
                sprintf('vedastro: time must use %s, got \'%s\': that date does not exist', self::WIRE_FORMAT, $value)
            );
        }

        if ((int) $hour > 23 || (int) $minute > 59) {
            throw new \InvalidArgumentException(
                sprintf('vedastro: time must use %s, got \'%s\': that clock time does not exist', self::WIRE_FORMAT, $value)
            );
        }

        // ZoneOffset.of equivalents reject an offset beyond +/-18:00.
        [$offsetHours, $offsetMinutes] = array_map('intval', explode(':', substr($offset, 1)));
        if ($offsetHours > 18 || $offsetMinutes > 59) {
            throw new \InvalidArgumentException(
                sprintf('vedastro: time must use %s, got \'%s\': invalid UTC offset', self::WIRE_FORMAT, $value)
            );
        }
    }
}
