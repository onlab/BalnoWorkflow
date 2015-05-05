<?php

namespace BalnoWorkflow\IntegrationTests\Interfaces;

use BalnoWorkflow\ContextInterface;

interface GuardPayment
{
    public function isAuthorized(ContextInterface $context);
}
