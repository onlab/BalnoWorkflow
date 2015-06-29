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
    protected $hasFinished = false;

    /**
     * @var ContextInterface
     */
    protected $parentContext;

    /**
     * @var \DateTime
     */
    protected $lastStateChangedAt;

    /**
     * @var string[]
     */
    protected $variables = [];

    /**
     * @var string[]
     */
    protected $stateHistory = [];

    /**
     * @param $workflowName
     * @param ContextInterface $parentContext
     */
    public function __construct($workflowName, ContextInterface $parentContext = null)
    {
        $this->workflowName = $workflowName;
        $this->parentContext = $parentContext;
    }

    /**
     * @return string
     */
    public function getWorkflowName()
    {
        return $this->workflowName;
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
        $this->lastStateChangedAt = new \DateTime();
        $this->stateHistory[] = $state;
        $this->currentState = $state;
        $this->childrenContexts = [];
    }

    /**
     * @return \DateTime
     */
    public function getLastStateChangedAt()
    {
        return $this->lastStateChangedAt;
    }

    /**
     * @param string $variableName
     * @return string
     */
    public function getVariable($variableName)
    {
        return isset($this->variables[$variableName]) ? $this->variables[$variableName] : null;
    }

    /**
     * @param string $variableName
     * @param string $content
     */
    public function setVariable($variableName, $content)
    {
        $this->variables[$variableName] = $content;
    }

    /**
     * @param string $variableName
     */
    public function unsetVariable($variableName)
    {
        if (isset($this->variables[$variableName])) {
            unset($this->variables[$variableName]);
        }
    }

    /**
     * @return string[]
     */
    public function getStateHistory()
    {
        return $this->stateHistory;
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
