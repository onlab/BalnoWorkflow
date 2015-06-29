<?php

namespace BalnoWorkflow\TestResource\Guard;

use BalnoWorkflow\ContextInterface;

interface FraudGuard
{
    public function requestSent(ContextInterface $context);
    public function isFraud(ContextInterface $context);
    public function isNotFraud(ContextInterface $context);
}
