<?php

namespace BalnoWorkflow\Handler;

use BalnoWorkflow\ContextInterface;

abstract class ContextHandler implements ContextHandlerInterface
{
    /**
     * @param ContextInterface $context
     * @param $workflowName
     * @return ContextInterface
     */
    public function forkContext(ContextInterface $context, $workflowName)
    {
        $childContext = $this->createChildContext($context, $workflowName);
        $context->addChildContext($childContext);

        return $childContext;
    }

    /**
     * @param ContextInterface $parentContext
     * @param string $workflowName
     * @return ContextInterface
     */
    abstract protected function createChildContext(ContextInterface $parentContext, $workflowName);

    /**
     * @param ContextInterface $context
     */
    public function finish(ContextInterface $context)
    {
        $context->finish();
    }
}
