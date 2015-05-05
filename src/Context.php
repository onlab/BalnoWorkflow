<?php

namespace BalnoWorkflow;

class Context implements ContextInterface
{
    /**
     * @var string
     */
    protected $currentState;

    /**
     * @var string
     */
    protected $workflowName;

    /**
     * @var ContextInterface[]
     */
    protected $childrenContexts = [];

    /**
     * @var bool
     */
    protected $hasFinished;

    /**
     * @var ContextInterface
     */
    protected $parentContext;

    /**
     * @param string $workflowName
     * @param bool $hasFinished
     */
    public function __construct($workflowName, $hasFinished = false)
    {
        $this->workflowName = $workflowName;
        $this->hasFinished = $hasFinished;
    }

    /**
     * @return string
     */
    public function getWorkflowName()
    {
        return $this->workflowName;
    }

    /**
     * @param ContextInterface $parentContext
     */
    public function setParentContext(ContextInterface $parentContext)
    {
        $this->parentContext = $parentContext;
    }

    /**
     * @return ContextInterface
     */
    public function getParentContext()
    {
        return $this->parentContext;
    }

    /**
     * @param ContextInterface $childContext
     */
    public function addChildContext(ContextInterface $childContext)
    {
        $this->childrenContexts[] = $childContext;
    }

    /**
     * @return ContextInterface[]
     */
    public function getChildrenContexts()
    {
        return $this->childrenContexts;
    }

    /**
     * @return ContextInterface[]
     */
    public function getActiveChildrenContexts()
    {
        $activeChildrenContexts = [];

        foreach ($this->childrenContexts as $childContext) {
            if (!$childContext->hasFinished()) {
                $activeChildrenContexts[] = $childContext;
            }
        }

        return $activeChildrenContexts;
    }

    /**
     * @return bool
     */
    public function hasActiveChildrenContexts()
    {
        return !empty($this->getActiveChildrenContexts());
    }

    /**
     * @return string
     */
    public function getCurrentState()
    {
        return $this->currentState;
    }

    /**
     * @param string $state
     */
    public function setCurrentState($state)
    {
        $this->currentState = $state;
    }

    /**
     * @return bool
     */
    public function hasFinished()
    {
        return $this->hasFinished;
    }

    public function finish()
    {
        $this->hasFinished = true;
    }
}
