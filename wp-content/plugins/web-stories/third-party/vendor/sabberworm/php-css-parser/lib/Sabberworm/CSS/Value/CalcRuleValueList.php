<?php

namespace Google\Web_Stories_Dependencies\Sabberworm\CSS\Value;

class CalcRuleValueList extends \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\RuleValueList
{
    public function __construct($iLineNo = 0)
    {
        parent::__construct(array(), ',', $iLineNo);
    }
    public function render(\Google\Web_Stories_Dependencies\Sabberworm\CSS\OutputFormat $oOutputFormat)
    {
        return $oOutputFormat->implode(' ', $this->aComponents);
    }
}
