<?php

namespace BalnoWorkflow\TestResource\Definitions;

use BalnoWorkflow\TestResource\TransitionEvents;

class WarehouseWorkflowDefinition implements WorkflowDefinitionInterface
{
    const NAME = 'warehouse_workflow';

    public static function getDefinition()
    {
        return [
            'waiting_order_packaging' => [
                targets => [
                    'order_packaged' => [ event => TransitionEvents::ORDER_PACKAGED ],
                    'order_canceled' => [ event => TransitionEvents::CANCEL_ORDER ],
                ],
            ],
            'order_packaged' => null,
            'order_canceled' => null,
        ];
    }
}
