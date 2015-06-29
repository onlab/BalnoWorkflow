<?php

namespace BalnoWorkflow\TestResource\Action;

use BalnoWorkflow\ContextInterface;

interface ShippingAction
{
    public function quote(ContextInterface $context);
    public function scheduleCarrier(ContextInterface $context);
}
