<?php

namespace BalnoWorkflow\Handler;

use BalnoWorkflow\ContextInterface;
use BalnoWorkflow\IntegrationTests\Context;

class ContextHandler implements ContextHandlerInterface
{
    /**
     * @param ContextInterface $context
     * @param $workflowName
     * @return Context
     */
    public function forkContext(ContextInterface $context, $workflowName)
    {
        $childContext = new Context($workflowName);
        $childContext->setParentContext($context);
        $context->addChildContext($childContext);

        return $childContext;
    }

    /**
     * @param Context $context
     */
    public function finish(ContextInterface $context)
    {
        $context->finish();
    }
}
