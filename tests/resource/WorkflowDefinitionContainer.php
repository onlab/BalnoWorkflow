<?php

namespace BalnoWorkflow\TestResource;

use BalnoWorkflow\TestResource\Definitions\InvoiceWorkflowDefinition;
use BalnoWorkflow\TestResource\Definitions\LogisticsWorkflowDefinition;
use BalnoWorkflow\TestResource\Definitions\OrderWorkflowDefinition;
use BalnoWorkflow\TestResource\Definitions\WarehouseWorkflowDefinition;

class WorkflowDefinitionContainer
{
    /**
     * @return \ArrayObject
     */
    public static function getTestDefinitions()
    {
        $definitionsContainer = new \ArrayObject();
        $definitionsContainer[OrderWorkflowDefinition::NAME] = OrderWorkflowDefinition::getDefinition();
        $definitionsContainer[InvoiceWorkflowDefinition::NAME] = InvoiceWorkflowDefinition::getDefinition();
        $definitionsContainer[WarehouseWorkflowDefinition::NAME] = WarehouseWorkflowDefinition::getDefinition();
        $definitionsContainer[LogisticsWorkflowDefinition::NAME] = LogisticsWorkflowDefinition::getDefinition();

        return $definitionsContainer;
    }
}
