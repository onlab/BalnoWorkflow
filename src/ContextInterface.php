<?php

namespace BalnoWorkflow;

interface ContextInterface
{
    /**
     * @return string
     */
    public function getWorkflowName();

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
     * @param string $variableName
     * @return string
     */
    public function getVariable($variableName);

    /**
     * @param string $variableName
     * @param string $content
     */
    public function setVariable($variableName, $content);

    /**
     * @param string $variableName
     */
    public function unsetVariable($variableName);

    /**
     * @return \DateTime
     */
    public function getLastStatusChangedAt();

    /**
     * @return string[]
     */
    public function getStatusHistory();

    /**
     * @return bool
     */
    public function hasFinished();

    public function finish();
}
