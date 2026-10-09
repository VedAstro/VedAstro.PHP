<?php

declare(strict_types=1);

/**
 * VedAstro PHP SDK.
 *
 * Include this file to load the whole library without Composer:
 *
 * ```php
 * require __DIR__ . '/vendor/vedastro/vedastro/src/VedAstro.php';
 * ```
 *
 * With Composer the autoloader does this for you, so only include it when vendoring the source
 * directly.
 */

// Core types first: the generated code references them in signatures and property types.
require_once __DIR__ . '/VedAstroException.php';
require_once __DIR__ . '/ApiException.php';
require_once __DIR__ . '/TransportResponse.php';
require_once __DIR__ . '/Transport.php';
require_once __DIR__ . '/StreamTransport.php';
require_once __DIR__ . '/CurlTransport.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/GeoLocation.php';
require_once __DIR__ . '/Time.php';
require_once __DIR__ . '/ApiResponse.php';

// Generated enums. Half of them are referenced by calculator signatures, so they load before
// the calculators themselves.
foreach ([
    'PlanetName',
    'HouseName',
    'ZodiacName',
    'ChartType',
    'DayOfWeek',
    'ConstellationName',
    'Avasta',
    'Ayanamsa',
    'Karana',
    'LunarDayGroup',
    'LunarMonth',
    'BirthTimeModelType',
    'DasaType',
    'EventName',
    'EventTag',
    'HoroscopeTag',
    'JHoraDasaYearDefinition',
    'ShoolaAntardasaMode',
    'TripatakiObjectGroup',
] as $vedastroEnum) {
    $vedastroEnumFile = __DIR__ . '/Generated/' . $vedastroEnum . '.php';
    if (is_file($vedastroEnumFile)) {
        require_once $vedastroEnumFile;
    }
}
unset($vedastroEnum, $vedastroEnumFile);

// Generated calculator surface: the optional-argument holders, the trait, then the classes
// that depend on them.
require_once __DIR__ . '/Generated/Options.php';
require_once __DIR__ . '/Generated/CalculatesApi.php';
require_once __DIR__ . '/Calculators.php';
require_once __DIR__ . '/VedAstroClient.php';
require_once __DIR__ . '/Calculate.php';
