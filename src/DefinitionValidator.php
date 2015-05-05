<?php

namespace BalnoWorkflow;

class DefinitionValidator
{
    protected function validateDefinition(array $definition)
    {
        foreach ($definition as $state => $stateProperties) {
            if (is_numeric($state)) {
                throw new \InvalidWorkflowDefinition(sprintf('State name must have a representative name. %s given', $state));
            }

            if (isset($stateProperties['targets'])) {
                foreach ($stateProperties['targets'] as $targetState => $targetProperties) {
                    if (!isset($definition[$targetState])) {
                        throw new \InvalidWorkflowDefinition(sprintf('Invalid target state "%s"', $targetState));
                    }
                }
            }

        }
    }
}
