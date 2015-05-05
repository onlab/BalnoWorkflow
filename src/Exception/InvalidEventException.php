<?php

namespace BalnoWorkflow\Exception;

class InvalidEventException extends \RuntimeException
{
    public function __construct($eventName)
    {
        $this->message = 'Event "' . $eventName . '" is not available in the current state';
    }
}
