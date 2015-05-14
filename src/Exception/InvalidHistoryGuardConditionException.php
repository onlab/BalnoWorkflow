<?php

namespace BalnoWorkflow\Exception;

class InvalidHistoryGuardConditionException extends \Exception
{
    public function __construct($condition)
    {
        $this->message = 'The given condition "' . $condition . '" is not available for history guards.';
    }
}
