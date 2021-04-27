<?php

namespace Google\Web_Stories_Dependencies\Sabberworm\CSS\Value;

use Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\ParserState;
use Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\UnexpectedTokenException;
class CalcFunction extends \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\CSSFunction
{
    const T_OPERAND = 1;
    const T_OPERATOR = 2;
    public static function parse(\Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\ParserState $oParserState)
    {
        $aOperators = array('+', '-', '*', '/');
        $sFunction = \trim($oParserState->consumeUntil('(', \false, \true));
        $oCalcList = new \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\CalcRuleValueList($oParserState->currentLine());
        $oList = new \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\RuleValueList(',', $oParserState->currentLine());
        $iNestingLevel = 0;
        $iLastComponentType = NULL;
        while (!$oParserState->comes(')') || $iNestingLevel > 0) {
            $oParserState->consumeWhiteSpace();
            if ($oParserState->comes('(')) {
                $iNestingLevel++;
                $oCalcList->addListComponent($oParserState->consume(1));
                $oParserState->consumeWhiteSpace();
                continue;
            } else {
                if ($oParserState->comes(')')) {
                    $iNestingLevel--;
                    $oCalcList->addListComponent($oParserState->consume(1));
                    $oParserState->consumeWhiteSpace();
                    continue;
                }
            }
            if ($iLastComponentType != \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\CalcFunction::T_OPERAND) {
                $oVal = \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\Value::parsePrimitiveValue($oParserState);
                $oCalcList->addListComponent($oVal);
                $iLastComponentType = \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\CalcFunction::T_OPERAND;
            } else {
                if (\in_array($oParserState->peek(), $aOperators)) {
                    if ($oParserState->comes('-') || $oParserState->comes('+')) {
                        if ($oParserState->peek(1, -1) != ' ' || !($oParserState->comes('- ') || $oParserState->comes('+ '))) {
                            throw new \Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\UnexpectedTokenException(" {$oParserState->peek()} ", $oParserState->peek(1, -1) . $oParserState->peek(2), 'literal', $oParserState->currentLine());
                        }
                    }
                    $oCalcList->addListComponent($oParserState->consume(1));
                    $iLastComponentType = \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\CalcFunction::T_OPERATOR;
                } else {
                    throw new \Google\Web_Stories_Dependencies\Sabberworm\CSS\Parsing\UnexpectedTokenException(\sprintf('Next token was expected to be an operand of type %s. Instead "%s" was found.', \implode(', ', $aOperators), $oVal), '', 'custom', $oParserState->currentLine());
                }
            }
            $oParserState->consumeWhiteSpace();
        }
        $oList->addListComponent($oCalcList);
        $oParserState->consume(')');
        return new \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\CalcFunction($sFunction, $oList, ',', $oParserState->currentLine());
    }
}
