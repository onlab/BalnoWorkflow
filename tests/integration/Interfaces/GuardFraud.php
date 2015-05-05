<?php

namespace BalnoWorkflow\IntegrationTests\Interfaces;

use BalnoWorkflow\ContextInterface;

interface GuardFraud
{
    public function requestSent(ContextInterface $context);
    public function isFraud(ContextInterface $context);
    public function isNotFraud(ContextInterface $context);
}
