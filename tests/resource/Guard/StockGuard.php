<?php

namespace BalnoWorkflow\TestResource\Guard;

use BalnoWorkflow\ContextInterface;

interface StockGuard
{
    public function isStocked(ContextInterface $context);
}
