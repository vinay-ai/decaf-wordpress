<?php

namespace Google\Web_Stories_Dependencies\Sabberworm\CSS\Value;

use Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\ParserState;
class URL extends \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\PrimitiveValue
{
    private $oURL;
    public function __construct(\Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\CSSString $oURL, $iLineNo = 0)
    {
        parent::__construct($iLineNo);
        $this->oURL = $oURL;
    }
    public static function parse(\Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\ParserState $oParserState)
    {
        $bUseUrl = $oParserState->comes('url', \true);
        if ($bUseUrl) {
            $oParserState->consume('url');
            $oParserState->consumeWhiteSpace();
            $oParserState->consume('(');
        }
        $oParserState->consumeWhiteSpace();
        $oResult = new \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\URL(\Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\CSSString::parse($oParserState), $oParserState->currentLine());
        if ($bUseUrl) {
            $oParserState->consumeWhiteSpace();
            $oParserState->consume(')');
        }
        return $oResult;
    }
    public function setURL(\Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\CSSString $oURL)
    {
        $this->oURL = $oURL;
    }
    public function getURL()
    {
        return $this->oURL;
    }
    public function __toString()
    {
        return $this->render(new \Google\Web_Stories_Dependencies\Sabberworm\CSS\OutputFormat());
    }
    public function render(\Google\Web_Stories_Dependencies\Sabberworm\CSS\OutputFormat $oOutputFormat)
    {
        return "url({$this->oURL->render($oOutputFormat)})";
    }
}
