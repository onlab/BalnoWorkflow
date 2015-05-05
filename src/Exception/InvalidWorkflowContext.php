<?php

namespace BalnoWorkflow\Exception;

class InvalidStateMachineContext extends \RuntimeException
{
    public function __construct()
    {
        $this->message = 'Given state machine context is in a unhandleable state';
    }
}
