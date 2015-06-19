<?php

namespace BalnoWorkflow\UnitTest;

use BalnoWorkflow\GraphvizWorkflowRenderer;
use BalnoWorkflow\UnitTest\Resources\WorkflowDefinitionContainer;

class GraphvizWorkflowRendererTest extends \PHPUnit_Framework_TestCase
{
    public function testGraphviz()
    {
        $renderer = new GraphvizWorkflowRenderer(WorkflowDefinitionContainer::getTestDefinitions(), 'nada');
        $renderer->renderWorkflowToStream('order_workflow');
    }
}
