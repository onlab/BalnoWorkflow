<?php

namespace BalnoWorkflow\IntegrationTests\Interfaces;

use BalnoWorkflow\ContextInterface;

interface OrderFacade
{
    public function notifyInvalidOrder(ContextInterface $context);
    public function notifyOrderSent(ContextInterface $context);
    public function notifyOrderCanceled(ContextInterface $context);
}
