<?php

namespace BalnoWorkflow\Exception;

class InvalidRunnableExpressionException extends \Exception
{
    public function __construct($expression)
    {
        $this->message = 'The provided expression "' . $expression . '" is not valid.';
    }
}
