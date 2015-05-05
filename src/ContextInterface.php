<?php

namespace BalnoWorkflow;

interface ContextInterface
{
    /**
     * @return string
     */
    public function getWorkflowName();

    /**
     * @param ContextInterface $parentContext
     */
    public function setParentContext(ContextInterface $parentContext);

    /**
     * @return ContextInterface
     */
    public function getParentContext();

    /**
     * @param ContextInterface $childContext
     */
    public function addChildContext(ContextInterface $childContext);

    /**
     * @return ContextInterface[]
     */
    public function getChildrenContexts();

    /**
     * @return ContextInterface[]
     */
    public function getActiveChildrenContexts();

    /**
     * @return bool
     */
    public function hasActiveChildrenContexts();

    /**
     * @return string
     */
    public function getCurrentState();

    /**
     * @param string $state
     */
    public function setCurrentState($state);

    /**
     * @return bool
     */
    public function hasFinished();

    public function finish();
}
