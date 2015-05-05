<?php

namespace BalnoWorkflow\IntegrationTests\Interfaces;

use BalnoWorkflow\ContextInterface;

interface SacFacade
{
    public function notifyInvalidOrder(ContextInterface $context);
    public function notifyFraudPending(ContextInterface $context);
}
