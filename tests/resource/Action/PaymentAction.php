<?php

namespace BalnoWorkflow\TestResource\Action;

use BalnoWorkflow\ContextInterface;

interface PaymentAction
{
    public function authorizeOrderPayment(ContextInterface $context);
    public function capturePayment(ContextInterface $context);
}
