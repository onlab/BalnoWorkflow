<?php

namespace BalnoWorkflow\TestResource\Action;

use BalnoWorkflow\ContextInterface;

interface StockAction
{
    public function checkOrderAvailability(ContextInterface $context);
    public function reserveOrderItems(ContextInterface $context);
}
