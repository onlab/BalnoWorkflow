<?php

namespace BalnoWorkflow\TestResource\Guard;

use BalnoWorkflow\ContextInterface;

interface ShippingGuard
{
    public function hasNoCarrierAvailable(ContextInterface $context);
}
