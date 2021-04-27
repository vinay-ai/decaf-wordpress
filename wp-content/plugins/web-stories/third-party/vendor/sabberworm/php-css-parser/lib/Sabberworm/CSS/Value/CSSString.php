<?php

namespace Google\Web_Stories_Dependencies\Sabberworm\CSS\Value;

use Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\ParserState;
use Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\SourceException;
class CSSString extends \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\PrimitiveValue
{
    private $sString;
    public function __construct($sString, $iLineNo = 0)
    {
        $this->sString = $sString;
        parent::__construct($iLineNo);
    }
    public static function parse(\Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\ParserState $oParserState)
    {
        $sBegin = $oParserState->peek();
        $sQuote = null;
        if ($sBegin === "'") {
            $sQuote = "'";
        } else {
            if ($sBegin === '"') {
                $sQuote = '"';
            }
        }
        if ($sQuote !== null) {
            $oParserState->consume($sQuote);
        }
        $sResult = "";
        $sContent = null;
        if ($sQuote === null) {
            // Unquoted strings end in whitespace or with braces, brackets, parentheses
            while (!\preg_match('/[\\s{}()<>\\[\\]]/isu', $oParserState->peek())) {
                $sResult .= $oParserState->parseCharacter(\false);
            }
        } else {
            while (!$oParserState->comes($sQuote)) {
                $sContent = $oParserState->parseCharacter(\false);
                if ($sContent === null) {
                    throw new \Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\SourceException("Non-well-formed quoted string {$oParserState->peek(3)}", $oParserState->currentLine());
                }
                $sResult .= $sContent;
            }
            $oParserState->consume($sQuote);
        }
        return new \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\CSSString($sResult, $oParserState->currentLine());
    }
    public function setString($sString)
    {
        $this->sString = $sString;
    }
    public function getString()
    {
        return $this->sString;
    }
    public function __toString()
    {
        return $this->render(new \Google\Web_Stories_Dependencies\Sabberworm\CSS\OutputFormat());
    }
    public function render(\Google\Web_Stories_Dependencies\Sabberworm\CSS\OutputFormat $oOutputFormat)
    {
        $sString = \addslashes($this->sString);
        $sString = \str_replace("\n", '\\A', $sString);
        return $oOutputFormat->getStringQuotingType() . $sString . $oOutputFormat->getStringQuotingType();
    }
}
