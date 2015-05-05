<?php

namespace BalnoWorkflow\IntegrationTests\Interfaces;

use BalnoWorkflow\ContextInterface;

interface GuardStock
{
    public function isStocked(ContextInterface $context);
}
