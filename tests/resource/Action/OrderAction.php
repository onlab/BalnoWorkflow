<?php

namespace BalnoWorkflow\TestResource\Action;

use BalnoWorkflow\ContextInterface;

interface OrderAction
{
    public function notifyInvalidOrder(ContextInterface $context);
    public function notifyOrderSent(ContextInterface $context);
    public function notifyOrderCanceled(ContextInterface $context);
}
