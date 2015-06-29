<?php

namespace BalnoWorkflow\TestResource\Guard;

use BalnoWorkflow\ContextInterface;

interface InvoiceGuard
{
    public function invoiceCreated(ContextInterface $context);
}
