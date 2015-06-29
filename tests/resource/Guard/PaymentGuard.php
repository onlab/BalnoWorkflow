<?php

namespace BalnoWorkflow\TestResource\Guard;

use BalnoWorkflow\ContextInterface;

interface PaymentGuard
{
    public function isAuthorized(ContextInterface $context);
}
