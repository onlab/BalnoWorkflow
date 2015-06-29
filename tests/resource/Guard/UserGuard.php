<?php

namespace BalnoWorkflow\TestResource\Guard;

use BalnoWorkflow\ContextInterface;

interface UserGuard
{
    public function isAllowedToExecute(ContextInterface $context);
}
