<?php

namespace BalnoWorkflow\IntegrationTests\Interfaces;

use BalnoWorkflow\ContextInterface;

interface PaymentFacade
{
    public function authorizeOrderPayment(ContextInterface $context);
    public function capturePayment(ContextInterface $context);
}
