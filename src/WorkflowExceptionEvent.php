<?php

namespace BalnoWorkflow;

class WorkflowExceptionEvent extends WorkflowEvent
{
    /**
     * @var \Exception
     */
    protected $exception;

    /**
     * @var string
     */
    protected $expression;

    public function __construct(ContextInterface $context, \Exception $exception, $expression)
    {
        parent::__construct($context);

        $this->exception = $exception;
        $this->expression = $expression;
    }

    /**
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }
}
