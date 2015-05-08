<?php

namespace BalnoWorkflow\Guard;

use BalnoWorkflow\ContextInterface;

class VarGuard
{
    /**
     * @param ContextInterface $context
     * @param string $variableName
     * @param string $content
     * @return bool
     */
    public function hasContent(ContextInterface $context, $variableName, $content)
    {
        return $context->getVariable($variableName) === $content;
    }
}
