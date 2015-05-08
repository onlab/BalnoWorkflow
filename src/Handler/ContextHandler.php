<?php

namespace BalnoWorkflow\Handler;

use BalnoWorkflow\ContextInterface;
use BalnoWorkflow\IntegrationTests\Context;

abstract class ContextHandler implements ContextHandlerInterface
{
    /**
     * @param ContextInterface $context
     * @param $workflowName
     * @return Context
     */
    public function forkContext(ContextInterface $context, $workflowName)
    {
        $childContext = $this->createChildContext($context, $workflowName);
        $context->addChildContext($childContext);

        return $childContext;
    }

    abstract protected function createChildContext(ContextInterface $parentContext, $workflowName);

    /**
     * @param Context $context
     */
    public function finish(ContextInterface $context)
    {
        $context->finish();
    }
}
