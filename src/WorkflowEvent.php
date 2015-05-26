<?php

namespace BalnoWorkflow;

use Symfony\Component\EventDispatcher\Event;

class WorkflowEvent extends Event
{
    /**
     * @var ContextInterface
     */
    protected $context;

    public function __construct(ContextInterface $context)
    {
        $this->context = $context;
    }

    /**
     * @return ContextInterface
     */
    public function getContext()
    {
        return $this->context;
    }
}
