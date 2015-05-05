<?php

namespace BalnoWorkflow\IntegrationTests;

use BalnoWorkflow\Context as BaseContext;
use BalnoWorkflow\ContextInterface;

class Context extends BaseContext
{
    /**
     * @var string[]
     */
    public $stateHistory;

    /**
     * @param string $state
     */
    public function setCurrentState($state)
    {
        $this->stateHistory[] = $state;

        parent::setCurrentState($state);
    }
}

