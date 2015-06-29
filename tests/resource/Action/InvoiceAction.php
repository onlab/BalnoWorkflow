<?php

namespace BalnoWorkflow\TestResource\Action;

use BalnoWorkflow\ContextInterface;

interface InvoiceAction
{
    public function generateInvoice(ContextInterface $context);
}
