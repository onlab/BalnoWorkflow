<?php

namespace BalnoWorkflow\IntegrationTests\Interfaces;

use BalnoWorkflow\ContextInterface;

interface GuardUser
{
    public function isAllowedToExecute(ContextInterface $context);
}
