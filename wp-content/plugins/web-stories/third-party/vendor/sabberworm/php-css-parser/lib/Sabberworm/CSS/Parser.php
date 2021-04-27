<?php

namespace Google\Web_Stories_Dependencies\Sabberworm\CSS;

use Google\Web_Stories_Dependencies\Sabberworm\CSS\CSSList\Document;
use Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\ParserState;
/**
 * Parser class parses CSS from text into a data structure.
 */
class Parser
{
    private $oParserState;
    /**
     * Parser constructor.
     * Note that that iLineNo starts from 1 and not 0
     *
     * @param $sText
     * @param Settings|null $oParserSettings
     * @param int $iLineNo
     */
    public function __construct($sText, \Google\Web_Stories_Dependencies\Sabberworm\CSS\Settings $oParserSettings = null, $iLineNo = 1)
    {
        if ($oParserSettings === null) {
            $oParserSettings = \Google\Web_Stories_Dependencies\Sabberworm\CSS\Settings::create();
        }
        $this->oParserState = new \Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\ParserState($sText, $oParserSettings, $iLineNo);
    }
    public function setCharset($sCharset)
    {
        $this->oParserState->setCharset($sCharset);
    }
    public function getCharset()
    {
        $this->oParserState->getCharset();
    }
    public function parse()
    {
        return \Google\Web_Stories_Dependencies\Sabberworm\CSS\CSSList\Document::parse($this->oParserState);
    }
}
