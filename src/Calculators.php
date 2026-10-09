<?php

declare(strict_types=1);

namespace VedAstro;

use VedAstro\Generated\CalculatesApi;

/**
 * The 684 VedAstro calculators.
 *
 * Obtain an instance from {@see VedAstroClient::calculate()} and call the calculator by name:
 *
 * ```php
 * $sun = $client->calculate()->planetRasiD1Sign(PlanetName::SUN, $birth);
 * ```
 *
 * The methods themselves live in the generated {@see CalculatesApi} trait, which is mixed in
 * here so the public class stays stable while the calculator surface is regenerated.
 */
class Calculators
{
    use CalculatesApi;

    public function __construct(private readonly VedAstroClient $vedAstro)
    {
    }
}
