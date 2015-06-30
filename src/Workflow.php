<?php

namespace BalnoWorkflow;

use BalnoWorkflow\Exception\InvalidEventException;
use BalnoWorkflow\Exception\InvalidRunnableExpressionException;
use BalnoWorkflow\Exception\InvalidWorkflowDefinitionException;
use BalnoWorkflow\Handler\ContextHandlerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Workflow
{
    const REGEX_DEFINITION = '
        (?(DEFINE)
            (?<singleQuotedString>\'[^\\\\\']*+(?:\\\\.[^\\\\\']*+)*+\')
            (?<doubleQuotedString>"[^\\\\"]*+(?:\\\\.[^\\\\"]*+)*+")
            (?<string>(?&singleQuotedString)|(?&doubleQuotedString))
            (?<boolean>true|false)
            (?<number>\d+(?:\.\d+)?)
            (?<array>\[(?:(?&arguments)|)])
            (?<argument>(?&string)|(?&boolean)|(?&number)|(?&array))
            (?<arguments>\s*(?&argument)\s*,(?&arguments)|\s*(?&argument)\s*)
        )
    ';

    /**
     * @var \ArrayAccess
     */
    protected $definitions;

    /**
     * @var \ArrayAccess
     */
    protected $actions;

    /**
     * @var \ArrayAccess
     */
    protected $guards;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var ContextHandlerInterface
     */
    protected $contextHandler;

    public function __construct(
        \ArrayAccess $definitions,
        \ArrayAccess $guards,
        \ArrayAccess $actions,
        EventDispatcherInterface $eventDispatcher,
        ContextHandlerInterface $contextHandler
    )
    {
        $this->definitions = $definitions;
        $this->guards = $guards;
        $this->actions = $actions;
        $this->eventDispatcher = $eventDispatcher;
        $this->contextHandler = $contextHandler;
    }

    /**
     * @param ContextInterface $context
     * @return array
     * @throws InvalidWorkflowDefinitionException
     */
    public function getAvailableEvents(ContextInterface $context)
    {
        $workingDefinition = $this->definitions[$context->getWorkflowName()];
        $this->ensureContextState($context, $workingDefinition);

        $currentStateProperties = $workingDefinition[$context->getCurrentState()];
        $availableEvents = [];
        if (isset($currentStateProperties['targets'])) {
            foreach ($currentStateProperties['targets'] as $transition) {
                if (isset($transition['event']) && !in_array($transition['event'], $availableEvents)) {
                    $availableEvents[] = $transition['event'];
                }
            }
        }

        foreach ($context->getActiveChildrenContexts() as $childContext) {
            $availableEvents = array_merge($availableEvents, $this->getAvailableEvents($childContext));
        }

        return array_unique($availableEvents);
    }

    protected function ensureContextState(ContextInterface $context, array $workingDefinition)
    {
        if ($context->getCurrentState() === null) {
            reset($workingDefinition);
            $context->setCurrentState(key($workingDefinition));
            $this->runStateActions($context, $workingDefinition, 'onEntry');

        } elseif (!array_key_exists($context->getCurrentState(), $workingDefinition)) {
            throw new InvalidWorkflowDefinitionException();
        }
    }

    /**
     * @param ContextInterface $context
     * @param string $event
     * @throws InvalidWorkflowDefinitionException
     */
    public function execute(ContextInterface $context, $event = null)
    {
        $workingDefinition = $this->definitions[$context->getWorkflowName()];

        $this->ensureContextState($context, $workingDefinition);

        $this->eventDispatcher->dispatch(WorkflowEvents::BEGIN_EXECUTION, new WorkflowEvent($context));
        $this->run($context, $workingDefinition, $event);
        $this->eventDispatcher->dispatch(WorkflowEvents::END_EXECUTION, new WorkflowEvent($context));
    }

    /**
     * @param $workingDefinition
     * @param ContextInterface $context
     * @param $event
     */
    protected function run(ContextInterface $context, array $workingDefinition, $event)
    {
        do {
            // Execute any parallel executions available in this context
            if ($context->hasActiveChildrenContexts()) {
                $exception = null;
                foreach ($context->getActiveChildrenContexts() as $childContext) {
                    try {
                        $this->execute($childContext, $event);

                    } catch (\Exception $e) {
                        // store the first workflow exception to throw away
                        if (!$exception) {
                            $exception = $e;
                        }
                    }
                }

                if ($exception) {
                    throw $exception;
                }
            }

            $currentStateProperties = $workingDefinition[$context->getCurrentState()];

            // Pause execution if any child is still running
            if ($context->hasActiveChildrenContexts()) {
                break;

            // End execution when there's no targets defined and try to continue the parent context
            } elseif (empty($currentStateProperties['targets'])) {
                $this->contextHandler->finish($context);

                break;
            }

            $nextState = $this->getTargetStateAvailable($context, $currentStateProperties['targets'], $event);
            if ($nextState === null) {
                break;
            }

            $this->moveToTargetState($context, $workingDefinition, $nextState);

            $currentStateProperties = $workingDefinition[$context->getCurrentState()];
            if (isset($currentStateProperties['parallel'])) {
                $this->forkWorkflow($context, $currentStateProperties['parallel']);
            }

            $event = null;
        } while (true);
    }

    /**
     * @param ContextInterface $context
     * @param array $targets
     * @param string $event
     * @return string
     */
    protected function getTargetStateAvailable(ContextInterface $context, $targets, $event)
    {
        $triggeredEventTransitions = [];
        $defaultTransitions = [];

        foreach ($targets as $targetState => $transition) {
            if (!isset($transition['event'])) {
                $defaultTransitions[] = [
                    'targetState' => $targetState ,
                    'transition' => $transition
                ];
            } elseif ($event !== null && $transition['event'] == $event) {
                $triggeredEventTransitions[] = [
                    'targetState' => $targetState ,
                    'transition' => $transition
                ];
            }
        }

        foreach ($triggeredEventTransitions + $defaultTransitions as $stateTransition) {
            if (!isset($stateTransition['transition']['guard']) || $this->runCommand($context, $this->guards, $stateTransition['transition']['guard'])) {
                return $stateTransition['targetState'];
            }
        }

        return null;
    }

    /**
     * @param ContextInterface $context
     * @param string $expression
     * @return mixed
     */
    protected function runCommand(ContextInterface $context, \ArrayAccess $container, $expression)
    {
        if (!preg_match('/' . self::REGEX_DEFINITION . '^\s*(?<service>[\w\.]+):(?<method>[\w]+)(?:\((?<method_arguments>(?&arguments)?)\))?\s*$/x', $expression, $serviceMatch)) {
            throw new InvalidRunnableExpressionException($expression);
        }

        $parameters = [ $context ];
        if (isset($serviceMatch['method_arguments'])) {
            $parameters = array_merge($parameters, json_decode('[' . $serviceMatch['method_arguments'] . ']', true));
        }

        try {
            $result = call_user_func_array([$container[$serviceMatch['service']], $serviceMatch['method']], $parameters);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage() . ' when running ' . $expression);
        }

        return $result;
    }

    /**
     * @param $workingDefinition
     * @param ContextInterface $context
     * @param $nextState
     * @param $transition
     */
    protected function moveToTargetState(ContextInterface $context, array $workingDefinition, $nextState)
    {
        $this->eventDispatcher->dispatch(WorkflowEvents::BEGIN_TRANSITION, new WorkflowEvent($context));

        $this->runStateActions($context, $workingDefinition, 'onExit');
        $context->setCurrentState($nextState);

        $this->eventDispatcher->dispatch(WorkflowEvents::STATE_CHANGED, new WorkflowEvent($context));

        $this->runStateActions($context, $workingDefinition, 'onEntry');

        $this->eventDispatcher->dispatch(WorkflowEvents::END_TRANSITION, new WorkflowEvent($context));
    }

    /**
     * @param ContextInterface $context
     * @param array $workingDefinition
     * @param string $stateActionType
     * @throws InvalidRunnableExpressionException
     * @throws \Exception
     */
    protected function runStateActions(ContextInterface $context, array $workingDefinition, $stateActionType)
    {
        $raiseEventQueue = [];

        if (isset($workingDefinition[$context->getCurrentState()][$stateActionType])) {
            foreach ($workingDefinition[$context->getCurrentState()][$stateActionType] as $stateTypeAction) {
                if (isset($stateTypeAction['action'])) {
                    $this->runCommand($context, $this->actions, $stateTypeAction['action']);

                } elseif (isset($stateTypeAction['raise'])) {
                    $raiseEventQueue[] = $stateTypeAction['raise'];
                }
            }
        }

        if (count($raiseEventQueue)) {
            while ($context->getParentContext()) {
                $context = $context->getParentContext();
            }

            foreach ($raiseEventQueue as $raiseEvent) {
                $this->execute($context, $raiseEvent);
            }
        }
    }

    /**
     * @param ContextInterface $context
     * @param array $subWorkflowNames
     */
    protected function forkWorkflow(ContextInterface $context, $subWorkflowNames)
    {
        foreach ($subWorkflowNames as $workflowName) {
            $this->contextHandler->forkContext($context, $workflowName);
        }
    }
}
