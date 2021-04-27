<?php

namespace Google\Web_Stories_Dependencies\AmpProject\Exception\Cli;

use Google\Web_Stories_Dependencies\AmpProject\Exception\AmpCliException;
use OutOfBoundsException;
/**
 * Exception thrown when an invalid option was provided to the CLI.
 *
 * @package ampproject/amp-toolbox
 */
final class InvalidOption extends \OutOfBoundsException implements \Google\Web_Stories_Dependencies\AmpProject\Exception\AmpCliException
{
    /**
     * Instantiate an InvalidOption exception for an unknown option that was passed to the CLI.
     *
     * @param string $option Unknown option that was passed to the CLI.
     * @return self
     */
    public static function forUnknownOption($option)
    {
        $message = "Unknown option: '{$option}'.";
        return new self($message, \Google\Web_Stories_Dependencies\AmpProject\Exception\AmpCliException::E_UNKNOWN_OPT);
    }
}
