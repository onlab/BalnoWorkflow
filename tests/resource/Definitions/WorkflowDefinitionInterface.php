<?php

namespace BalnoWorkflow\TestResource\Definitions;

const targets = 'targets';
const guard = 'guard';
const action = 'action';
const event = 'event';
const raise = 'raise';
const onEntry = 'onEntry';
const onExit = 'onExit';
const parallel = 'parallel';

interface WorkflowDefinitionInterface
{
    public static function getDefinition();
}
