<?php

namespace Google\Web_Stories_Dependencies\Sabberworm\CSS\Value;

use Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\ParserState;
use Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\UnexpectedTokenException;
class LineName extends \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\ValueList
{
    public function __construct($aComponents = array(), $iLineNo = 0)
    {
        parent::__construct($aComponents, ' ', $iLineNo);
    }
    public static function parse(\Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\ParserState $oParserState)
    {
        $oParserState->consume('[');
        $oParserState->consumeWhiteSpace();
        $aNames = array();
        do {
            if ($oParserState->getSettings()->bLenientParsing) {
                try {
                    $aNames[] = $oParserState->parseIdentifier();
                } catch (\Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\UnexpectedTokenException $e) {
                    if (!$oParserState->comes(']')) {
                        throw $e;
                    }
                }
            } else {
                $aNames[] = $oParserState->parseIdentifier();
            }
            $oParserState->consumeWhiteSpace();
        } while (!$oParserState->comes(']'));
        $oParserState->consume(']');
        return new \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\LineName($aNames, $oParserState->currentLine());
    }
    public function __toString()
    {
        return $this->render(new \Google\Web_Stories_Dependencies\Sabberworm\CSS\OutputFormat());
    }
    public function render(\Google\Web_Stories_Dependencies\Sabberworm\CSS\OutputFormat $oOutputFormat)
    {
        return '[' . parent::render(\Google\Web_Stories_Dependencies\Sabberworm\CSS\OutputFormat::createCompact()) . ']';
    }
}
