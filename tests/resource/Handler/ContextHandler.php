<?php

namespace BalnoWorkflow\TestResource\Handler;

use BalnoWorkflow\ContextInterface;
use BalnoWorkflow\Handler\ContextHandler as BaseContextHandler;
use BalnoWorkflow\TestResource\Context;

class ContextHandler extends BaseContextHandler
{
    /**
     * @param ContextInterface $parentContext
     * @param string $workflowName
     * @return ContextInterface
     */
    protected function createChildContext(ContextInterface $parentContext, $workflowName)
    {
        return new Context($workflowName, $parentContext);
    }
}
