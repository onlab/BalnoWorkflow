BalnoWorkflow
=============
> **IMPORTANT**: This is project is still alpha. Don't use it in production until some tests are completed.

BalnoWorkflow is a workflow engine built for **PHP 5.5+** based on some other Workflows and State Machines that does
not have all features in the same lib.

This workflow gives to you the following feature:

- nice syntax to setup the workflow based on SCXML (http://www.w3.org/TR/scxml) structure.
- easy setup for parallel workflows (fork/merge).
- paused workflow can be resumed by a event trigger.
- no event-based transitions run automatically when guard condition (if was set) is satisfied.
- guards and actions configuration as service using Pimple as DI container.
- events available to lock control, log or anything else you want to implement with event listeners.

Workflow Events to Subscribe
----------------------------

You can check the events available in the *interface* `WorkflowEvents`:

- **begin_execution**: triggered when the workflow execution will start.
- **end_execution**: triggered when the workflow execution is done (paused or finished).
- **start_transition**: before onExit actions when changing the workflow state.
- **state_changed**: between onExit and onEntry actions just after setting the current state on context.
- **end_transition**: after onEntry actions when changed the workflow current state.

Basic Definition Sample
-----------------------

Always the first state defined will be set as initial state in the case below, the 'state_1' state is set as
initial state.

```php
use BalnoWorkflow\DefinitionsContainer;

$definitionsContainer = new DefinitionsContainer();
$definitionsContainer->addDefinition('sample_workflow', [
    'state_1' => [
        targets => [
            'state_2' => null,
        ],
        onExit => [
            [ action => 'pimple_service:method1' ],
        ],
    ],
    'state_2' => [
        targets => [
            'state_3' => [ event => 'some_event' ],
            'state_5' => [ guard => 'balno.workflow.guard.timer:hasTimedOut("30m")' ],
        ],
        onEntry => [
            [ action => 'pimple_service1:method' ],
            [ action => 'pimple_service2:method' ],
        ],
    ],
    'state_3' => [
        targets => [
            'state_4' => null,
        ],
        parallel => [
            'forked_workflow1',
            'forked_workflow2',
        ],
    ],
    'state_4' => [
        onEntry' => [
            [ action => 'pimple_service:method2("param")' ],
        ],
    ],
    'state_5' => null
]);
$definitionsContainer->addDefinition('forked_workflow1', [ ... ]);
$definitionsContainer->addDefinition('forked_workflow2', [ ... ]);
```

Given a new `Context` to execute...

```php
$context = new Context();

$workflow = new Workflow(...);
$workflow->execute($context)
```
 
... this workflow will execute the below history:

> **trigger** `begin_execution` listeners
>
>> **IMPORTANT:** initial state will not trigger onEntry actions
>
> ... check for default (no event set) transitions (found transition without guard to state_2)
>
> **trigger** `begin_transition` listeners
>
> **execute** state_1 `onExit` actions
>
> **trigger** `state_changed` listeners
>
> **execute** state_2 `onEntry` actions
>
> **trigger** `end_transition` listeners
>
> ... check for default transitions (found one with guard)
>
> **execute** guard `balno.workflow.guard.timer:hasTimedOut("30m")` that returns `false`
>
> **trigger** `end_execution` listeners

Now the state is in a paused state. To move forward the workflow must be executed after 30m (to satisfy the configured
guard timeout) or be executed with an event `some_event`.

To trigger the event you must run the code below (imagine that you is resuming the workflow with a persisted context):

```
$context = $myContextService->getSubjectWorkflowContext($subject);
$workflow->execute($context, 'some_event');
```

.. or if you just want to resume the workflow to reach the timeout condition:
```
$context = $myContextService->getSubjectWorkflowContext($subject);
$workflow->execute($context);
```

Actions
-------

Every action or guard are executed by the workflow passing the `Context` object as first parameter followed by the
parameters configured on the action or guard.

Given a guard `guard.example:someGuardCondition("test1", 2)` the method must be something like this:

```
class GuardExample
{
    public function someGuardCondition(ContextInterface $context, $parameter1, $parameter2)
    {
        ...
    }
}
```

Parallel Execution
------------------

Parallel execution is a special state that can't forward to an other state (even by an event) until all parallel
workflows are finished.

From the [Basic Definition Sample]:
```
state_3
   |
   |------------v--------------------v
   |            |                    |
   |      forked_workflow1     forked_workflow2
   |            |                    |
   |------------^--------------------^
   |
state_4
```

Since PHP unfortunately does not work with threads out-of-the-box the BalnoWorkflow will execute first the
`forked_workflow1` then `forked_workflow2`. So I recommend to place the fast process first then the slowest.

But imagine you need to send an e-mail to your client to confirm his e-mail (you're using a slow SMTP server)
on the `forked_workflow2` and the `forked_workflow2` will prepare an order to the product factory. Sending an
e-mail to the client will be better then creating an order to the factory. So, in this case, to satisfy the customer,
will be better to execute the slowest workflow first.

If you really need to execute in parallel you may setup a initial state without a default transition to pause one or
both workflows then run each workflow in kind of worker.

**IMPORTANT**: the workflow will automatically try to resume the parent context when you resume a child workflow
and it finishes. So, if you're really trying to parallelize this sub workflows ensure that your locking system is
compatible with this scenario.
