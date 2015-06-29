<?php

namespace BalnoWorkflow\TestResource\Definitions;

use BalnoWorkflow\TestResource\TransitionEvents;

class LogisticsWorkflowDefinition implements WorkflowDefinitionInterface
{
    const NAME = 'logistics_workflow';

    public static function getDefinition()
    {
        return [
            'quoting_shipment' => [
                targets => [
                    'waiting_carrier' => null,
                ],
                onEntry => [
                    [ action => 'shipping:quote' ],
                    [ action => 'shipping:scheduleCarrier' ],
                ],
            ],
            'waiting_carrier' => [
                targets => [
                    'order_canceled' => [ event => TransitionEvents::CANCEL_ORDER ],
                    'order_sent' => [ event => TransitionEvents::ORDER_SENT ],
                ],
            ],
            'order_sent' => null,
            'order_canceled' => null,
        ];
    }
}
