<?php

namespace BalnoWorkflow;

use BalnoWorkflow\Exception\InvalidEventException;
use BalnoWorkflow\Exception\InvalidWorkflowDefinition;
use BalnoWorkflow\Handler\ContextHandlerInterface;
use Pimple\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Workflow
{
    /**
     * @var DefinitionsContainer
     */
    protected $definitions;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var ContextHandlerInterface
     */
    protected $contextHandler;

    /**
     * @var Container
     */
    protected $services;

    public function __construct(
        DefinitionsContainer $definitions,
        Container $services,
        EventDispatcherInterface $eventDispatcher,
        ContextHandlerInterface $contextHandler
    )
    {
        $this->definitions = $definitions;
        $this->services = $services;
        $this->eventDispatcher = $eventDispatcher;
        $this->contextHandler = $contextHandler;
    }

    /**
     * @param ContextInterface $context
     * @return array
     * @throws InvalidWorkflowDefinition
     */
    public function getAvailableEvents(ContextInterface $context)
    {
        $workingDefinition = $this->definitions->getDefinition($context->getWorkflowName());
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

        return $availableEvents;
    }

    protected function ensureContextState(ContextInterface $context, array $workingDefinition)
    {
        if ($context->getCurrentState() === null) {
            reset($workingDefinition);
            $context->setCurrentState(key($workingDefinition));

        } elseif (!array_key_exists($context->getCurrentState(), $workingDefinition)) {
            throw new InvalidWorkflowDefinition();
        }
    }

    /**
     * @param ContextInterface $context
     * @param string $event
     * @throws InvalidWorkflowDefinition
     */
    public function execute(ContextInterface $context, $event = null)
    {
        $this->eventDispatcher->dispatch(WorkflowEventContraint::BEGIN_EXECUTION, new WorkflowEvent($context));
        $workingDefinition = $this->definitions->getDefinition($context->getWorkflowName());

        $this->ensureContextState($context, $workingDefinition);

        $this->run($context, $workingDefinition, $event);
        $this->eventDispatcher->dispatch(WorkflowEventContraint::END_EXECUTION, new WorkflowEvent($context));
    }

    /**
     * @param $workingDefinition
     * @param ContextInterface $context
     * @param $event
     */
    protected function run(ContextInterface $context, array $workingDefinition, $event)
    {
        $currentStateProperties = $workingDefinition[$context->getCurrentState()];

        do {
            // Execute any parallel executions available in this context
            if ($context->hasActiveChildrenContexts()) {
                foreach ($context->getActiveChildrenContexts() as $childContext) {
                    $this->execute($childContext);
                }
            }

            // Pause execution if any child is still running
            if ($context->hasActiveChildrenContexts()) {
                break;

            // End execution when there's no targets defined and try to continue the parent context
            } elseif (empty($currentStateProperties['targets'])) {
                $this->contextHandler->finish($context);

                if ($context->getParentContext()) {
                    $this->execute($context->getParentContext());
                }

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

        if ($event !== null && empty($triggeredEventTransitions)) {
            throw new InvalidEventException($event);
        }

        foreach ($triggeredEventTransitions + $defaultTransitions as $stateTransition) {
            if (!isset($stateTransition['transition']['guard']) || $this->runAction($context, $stateTransition['transition']['guard'])) {
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
    protected function runAction(ContextInterface $context, $expression)
    {
        preg_match('/^(?<service>.+?)\:(?<method>.+?)(?:\((?<parameters_bag>.+?)\))?$/', $expression, $serviceMatch);

        if (isset($serviceMatch['parameters_bag'])) {
            preg_match_all('/[,\s"]*([^,"]+)/', $serviceMatch['parameters_bag'], $parametersMatch);
            $parameters = [ $context ] + $parametersMatch[1];
        } else {
            $parameters = [ $context ];
        }

        $result = call_user_func_array([ $this->services[$serviceMatch['service']], $serviceMatch['method'] ], $parameters);

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
        $this->eventDispatcher->dispatch(WorkflowEventContraint::BEGIN_TRANSITION, new WorkflowEvent($context));

        // Run onExit actions
        if (isset($workingDefinition[$context->getCurrentState()]['onExit'])) {
            foreach ($workingDefinition[$context->getCurrentState()]['onExit'] as $onExitAction) {
                $this->runAction($context, $onExitAction['action']);
            }
        }

        $context->setCurrentState($nextState);
        $this->eventDispatcher->dispatch(WorkflowEventContraint::STATE_CHANGED, new WorkflowEvent($context));

        // Run onEntry actions
        if (isset($workingDefinition[$context->getCurrentState()]['onEntry'])) {
            foreach ($workingDefinition[$context->getCurrentState()]['onEntry'] as $onEntryAction) {
                $this->runAction($context, $onEntryAction['action']);
            }
        }

        $this->eventDispatcher->dispatch(WorkflowEventContraint::END_TRANSITION, new WorkflowEvent($context));
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
