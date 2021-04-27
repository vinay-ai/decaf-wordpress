<?php

namespace Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error;

use Google\Web_Stories_Dependencies\AmpProject\Dom\Element;
use Google\Web_Stories_Dependencies\AmpProject\Dom\ElementDump;
use Google\Web_Stories_Dependencies\AmpProject\Exception\MaxCssByteCountExceeded;
use Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error;
/**
 * Optimizer error object for when server-side rendering cannot be performed.
 *
 * @package ampproject/amp-toolbox
 */
final class CannotPerformServerSideRendering implements \Google\Web_Stories_Dependencies\AmpProject\Optimizer\Error
{
    use ErrorProperties;
    const INVALID_INPUT_WIDTH = 'Cannot perform serverside rendering, invalid input width: ';
    const INVALID_INPUT_HEIGHT = 'Cannot perform serverside rendering, invalid input height: ';
    const UNSUPPORTED_LAYOUT = 'Cannot perform serverside rendering, unsupported layout: ';
    const EXCEEDED_MAX_CSS_BYTE_COUNT = 'Cannot perform serverside rendering, exceeded maximum CSS byte count: ';
    /**
     * Instantiate a CannotPerformServerSideRendering object for an element with an invalid input width.
     *
     * @param Element $element Element that has an invalid input width.
     * @return self
     */
    public static function fromInvalidInputWidth(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element)
    {
        return new self(self::INVALID_INPUT_WIDTH . new \Google\Web_Stories_Dependencies\AmpProject\Dom\ElementDump($element));
    }
    /**
     * Instantiate a CannotPerformServerSideRendering object for an element with an invalid input height.
     *
     * @param Element $element Element that has an invalid input height.
     * @return self
     */
    public static function fromInvalidInputHeight(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element)
    {
        return new self(self::INVALID_INPUT_HEIGHT . new \Google\Web_Stories_Dependencies\AmpProject\Dom\ElementDump($element));
    }
    /**
     * Instantiate a CannotPerformServerSideRendering object for an element with an invalid input height.
     *
     * @param Element $element Element that has an invalid input height.
     * @param string  $layout  Resulting layout.
     * @return self
     */
    public static function fromUnsupportedLayout(\Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element, $layout)
    {
        return new self(self::UNSUPPORTED_LAYOUT . new \Google\Web_Stories_Dependencies\AmpProject\Dom\ElementDump($element) . " => {$layout}");
    }
    /**
     * Instantiate a CannotPerformServerSideRendering object for a MaxCssByteCountExceeded exception.
     *
     * @param MaxCssByteCountExceeded $exception Caught exception.
     * @param Element                 $element   Element that caused the exception.
     * @return self
     */
    public static function fromMaxCssByteCountExceededException(\Google\Web_Stories_Dependencies\AmpProject\Exception\MaxCssByteCountExceeded $exception, \Google\Web_Stories_Dependencies\AmpProject\Dom\Element $element)
    {
        return new self(self::EXCEEDED_MAX_CSS_BYTE_COUNT . new \Google\Web_Stories_Dependencies\AmpProject\Dom\ElementDump($element) . " => {$exception->getMessage()}");
    }
}
