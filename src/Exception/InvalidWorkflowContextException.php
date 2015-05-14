<?php

namespace BalnoWorkflow\Exception;

class InvalidStateMachineContextException extends \RuntimeException
{
    public function __construct()
    {
        $this->message = 'Given state machine context is in a unhandleable state';
    }
}
