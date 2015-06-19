<?php

namespace BalnoWorkflow;

class GraphvizWorkflowRenderer
{
    /**
     * @var \ArrayAccess
     */
    protected $definitions;

    /**
     * @var string
     */
    protected $graphvizPath;

    /**
     * @param \ArrayAccess $definitions
     * @param string $graphviz
     */
    public function __construct(\ArrayAccess $definitions, $graphvizPath)
    {
        $this->definitions = $definitions;
        $this->graphvizPath = $graphvizPath;
    }

    /**
     * @param string $workflowName
     */
    public function renderWorkflowToStream($workflowName)
    {
        $this->runGraphviz($this->getWorkflowDotRepresentation($workflowName));
    }

    /**
     * @param $workflowName
     * @return string
     */
    public function getWorkflowDotRepresentation($workflowName)
    {
        reset($this->definitions[$workflowName]);
        $digraph = 'digraph G { compound=true;'. $this->processWorkflowConnections($workflowName) . '}';

        return $digraph;
    }

    /**
     * @param string $workflowName
     * @return string
     */
    protected function processWorkflowConnections($workflowName)
    {
        $digraph = $workflowName . '_' . key($this->definitions[$workflowName]) . ' [shape=box];';

        foreach ($this->definitions[$workflowName] as $state => $stateProperties) {
            $workflowStateName = $workflowName . '_' . $state;

            if (isset($stateProperties['parallel'])) {
                $forkName = $workflowStateName . '_fork';
                $mergeName = $workflowStateName . '_merge';

                $digraph .= sprintf('%s -> %s;', $workflowStateName, $forkName);
                $digraph .= $forkName . ' [shape=point];';
                $digraph .= $mergeName . ' [shape=point];';

                foreach ($stateProperties['parallel'] as $subWorkflowName) {
                    reset($this->definitions[$subWorkflowName]);
                    $firstNodeSubWorkflow = $subWorkflowName . '_' . key($this->definitions[$subWorkflowName]);
                    $endDummyNodeSubWorkflow = $subWorkflowName . '_INVISIBLE_NODE';

                    $digraph .= sprintf('subgraph cluster_%s { color=black; label="%1$s";', $subWorkflowName);
                    $digraph .= $this->processWorkflowConnections($subWorkflowName);
                    $digraph .= $endDummyNodeSubWorkflow . '[shape=point style=invis];';
                    $digraph .= '}';

                    $digraph .= sprintf('%s -> %s [lhead=cluster_%s];', $forkName, $firstNodeSubWorkflow, $subWorkflowName);
                    $digraph .= sprintf('%s -> %s [ltail=cluster_%s];', $endDummyNodeSubWorkflow, $mergeName, $subWorkflowName);
                }

                $workflowStateName = $mergeName;
            }

            if (isset($stateProperties['targets'])) {
                foreach ($stateProperties['targets'] as $targetState => $targetGuard) {
                    $digraph .= sprintf('%s -> %s [label="%s"];', $workflowStateName, $workflowName . '_' . $targetState, isset($targetGuard['guard']) ? preg_quote($targetGuard['guard'], '"') : null);
                }
                $digraph .= sprintf('%s [label=%s];', $workflowStateName, $state);
            } else {
                $digraph .= sprintf('%s [label=%s, shape=doublecircle];', $workflowStateName, $state);
            }
        }

        return $digraph;
    }

    /**
     * @param string $digraph
     */
    protected function runGraphviz($digraph)
    {

    }
}
