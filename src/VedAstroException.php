<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * Base class for every failure raised by the SDK.
 *
 * All failures are unchecked in the PHP sense: callers are never forced into a try block, but
 * they can catch this type to handle anything the SDK throws.
 */
class VedAstroException extends \RuntimeException
{
}
