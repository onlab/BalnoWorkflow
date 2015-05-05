<?php

namespace BalnoWorkflow\IntegrationTests\Interfaces;

use BalnoWorkflow\ContextInterface;

interface StockFacade
{
    public function checkOrderAvailability(ContextInterface $context);
    public function reserveOrderItems(ContextInterface $context);
}
