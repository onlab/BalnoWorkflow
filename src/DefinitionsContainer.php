<?php

namespace BalnoWorkflow;

class DefinitionsContainer
{
    /**
     * @var array
     */
    protected $definitions;

    /**
     * @param string $name
     * @param array $definition
     */
    public function addDefinition($name, array $definition)
    {
        $this->definitions[$name] = $definition;
    }

    /**
     * @param string $definitionName
     * @return array
     */
    public function getDefinition($name)
    {
        return $this->definitions[$name];
    }
}
