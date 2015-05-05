<?php

namespace BalnoWorkflow\Handler;

use BalnoWorkflow\ContextInterface;

interface ContextHandlerInterface
{
    /**
     * @param ContextInterface $context
     * @param string $workflowName
     * @return ContextInterface
     */
    public function forkContext(ContextInterface $context, $workflowName);

    /**
     * @param ContextInterface $context
     */
    public function finish(ContextInterface $context);
}
