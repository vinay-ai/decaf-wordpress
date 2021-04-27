<?php

namespace Google\Web_Stories_Dependencies\Sabberworm\CSS\Value;

class RuleValueList extends \Google\Web_Stories_Dependencies\Sabberworm\CSS\Value\ValueList
{
    public function __construct($sSeparator = ',', $iLineNo = 0)
    {
        parent::__construct(array(), $sSeparator, $iLineNo);
    }
}
