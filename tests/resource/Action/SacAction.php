<?php

namespace BalnoWorkflow\TestResource\Action;

use BalnoWorkflow\ContextInterface;

interface SacAction
{
    public function notifyInvalidOrder(ContextInterface $context);
    public function notifyFraudPending(ContextInterface $context);
}
