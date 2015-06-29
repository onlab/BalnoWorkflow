<?php

namespace BalnoWorkflow\TestResource\Definitions;

use BalnoWorkflow\TestResource\TransitionEvents;

class InvoiceWorkflowDefinition implements WorkflowDefinitionInterface
{
    const NAME = 'invoice_workflow';

    public static function getDefinition()
    {
        return [
            'generating_invoice' => [
                targets => [
                    'waiting_invoice_generation' => null,
                ],
                onEntry => [
                    [ action => 'invoice:generateInvoice' ],
                ],
            ],
            'waiting_invoice_generation' => [
                targets => [
                    'order_canceled' => [ event => TransitionEvents::CANCEL_ORDER ],
                    'invoice_generated' => [ guard => 'invoice:invoiceCreated' ],
                    'clarify_invoice_generation' => [ guard => 'timer:hasTimedOut("1d")'],
                ],
            ],
            'clarify_invoice_generation' => [
                targets => [
                    'order_canceled' => [ event => TransitionEvents::CANCEL_ORDER ],
                    'retry_invoice_generation' => [ event => TransitionEvents::RETRY_INVOICE_GENERATION ],
                ],
            ],
            'invoice_generated' => null,
            'order_canceled' => null,
        ];
    }
}
