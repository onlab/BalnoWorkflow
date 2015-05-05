<?php

namespace BalnoWorkflow\IntegrationTests;

use BalnoWorkflow\DefinitionsContainer;
use BalnoWorkflow\Handler\ContextHandler;
use BalnoWorkflow\IntegrationTests\Interfaces\FraudFacade;
use BalnoWorkflow\IntegrationTests\Interfaces\GuardFraud;
use BalnoWorkflow\IntegrationTests\Interfaces\GuardPayment;
use BalnoWorkflow\IntegrationTests\Interfaces\GuardStock;
use BalnoWorkflow\IntegrationTests\Interfaces\OrderFacade;
use BalnoWorkflow\IntegrationTests\Interfaces\PaymentFacade;
use BalnoWorkflow\IntegrationTests\Interfaces\SacFacade;
use BalnoWorkflow\IntegrationTests\Interfaces\StockFacade;
use BalnoWorkflow\IntegrationTests\Interfaces\TransitionEvents;
use BalnoWorkflow\Workflow;
use Pimple\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;

const targets = 'targets';
const guard = 'guard';
const action = 'action';
const event = 'event';
const type = 'type';
const onEntry = 'onEntry';
const onExit = 'onExit';
const parallel = 'parallel';

class WorkflowTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array
     */
    protected $definitionsContainer;

    /**
     * @var array
     */
    protected $workflowHistory;

    protected function setUp()
    {
        $this->definitionsContainer = new DefinitionsContainer();
        $this->definitionsContainer->addDefinition('order_workflow', $this->getOrderWorkflow());
        $this->definitionsContainer->addDefinition('invoice_workflow', $this->getParallelWorkflowNonStop());
        $this->definitionsContainer->addDefinition('warehouse_workflow', $this->getParallelWorkflowRequireEvent());
        $this->definitionsContainer->addDefinition('logistics_workflow', $this->getParallelWorkflowRequireEvent());
    }

    protected function getOrderWorkflow()
    {
        return [
            'new' => [
                targets => [
                    'check_stock_availability' => null,
                ],
            ],
            'check_stock_availability' => [
                targets => [
                    'authorize_payment' => [ guard => 'test.guard.stock:isStocked' ],
                    'invalid' => null,
                ],
                onEntry => [
                    [ action => 'stock.facade:checkOrderAvailability' ],
                ],
            ],
            'authorize_payment' => [
                targets => [
                    'request_fraud_check' => [ guard => 'test.guard.payment:isAuthorized' ],
                    'invalid' => null,
                ],
                onEntry => [
                    [ action => 'payment.facade:authorizeOrderPayment' ],
                ],
            ],
            'request_fraud_check' => [
                targets => [
                    'waiting_fraud_check_response' => [ guard => 'test.guard.fraud:requestSent' ],
                    'request_fraud_check' => [ guard => 'test.guard.timer:hasTimedOut("30m")' ],
                ],
                onEntry => [
                    [ action => 'fraud.facade:requestFraudAnalysis' ],
                ],
            ],
            'waiting_fraud_check_response' => [
                targets => [
                    'canceled' => [ guard => 'test.guard.fraud:isFraud' ],
                    'capture_payment' => [ guard => 'test.guard.fraud:isNotFraud' ],
                    'clarify_fraud' => [ guard => 'test.guard.timer:hasTimedOut("1d")' ],
                ],
            ],
            'clarify_fraud' => [
                targets => [
                    'request_fraud_check' => [
                        event => TransitionEvents::RETRY_REQUEST_FRAUD
                    ],
                    'capture_payment' => [
                        event => TransitionEvents::NOT_FRAUD,
                        guard => 'test.guard.user:isAllowedToExecute'
                    ],
                    'canceled' => [
                        event => TransitionEvents::CANCEL
                    ],
                ],
                onEntry => [
                    [ action => 'sac.facade:notifyFraudPending' ],
                ],
            ],
            'capture_payment' => [
                targets => [
                    'package_process' => null,
                ],
                onEntry => [
                    [ action  => 'payment.facade:capturePayment' ],
                    [ action  => 'stock.facade:reserveOrderItems' ],
                ],
            ],
            'package_process' => [
                targets => [
                    'sent' => null,
                ],
                parallel => [
                    'invoice_workflow',
                    'warehouse_workflow',
                    'logistics_workflow',
                ],
            ],
            'sent' => [
                onEntry => [
                    [ action => 'order.facade:notifyOrderSent' ],
                ],
            ],
            'invalid' => [
                onEntry => [
                    [ action => 'sac.facade:notifyInvalidOrder' ],
                    [ action => 'order.facade:notifyInvalidOrder' ],
                ],
            ],
            'canceled' => [
                onEntry => [
                    [ action => 'order.facade:notifyOrderCanceled' ],
                ],
            ],
        ];
    }

    protected function getParallelWorkflowNonStop()
    {
        return [
            'state1' => [
                targets => [
                    'state2' => null,
                ],
            ],
            'state2' => null,
        ];
    }

    protected function getParallelWorkflowRequireEvent()
    {
        return [
            'state1' => [
                targets => [
                    'state2' => [ event => TransitionEvents::ORDER_PACKAGED ],
                ],
            ],
            'state2' => null,
        ];
    }

    public function testOutOfStockFlow()
    {
        $services = new Container();
        $eventDispatcher = new EventDispatcher();
        $context = new Context('order_workflow');
        $contextHandler = new ContextHandler();

        $services['test.guard.stock'] = $this->prophesize(GuardStock::class)
            ->isStocked($context)->willReturn(false)->getObjectProphecy()
            ->reveal();

        $services['stock.facade'] = $this->prophesize(StockFacade::class)
            ->checkOrderAvailability($context)->getObjectProphecy()
            ->reveal();

        $services['sac.facade'] = $this->prophesize(SacFacade::class)
            ->notifyInvalidOrder($context)->getObjectProphecy()
            ->reveal();

        $services['order.facade'] = $this->prophesize(OrderFacade::class)
            ->notifyInvalidOrder($context)->getObjectProphecy()
            ->reveal();

        $services['test.guard.payment'] = $this->prophesize(GuardPayment::class)
            ->isAuthorized($context)->willreturn(true)->getObjectProphecy()
            ->reveal();

        $workflow = new Workflow(
            $this->definitionsContainer,
            $services,
            $eventDispatcher,
            $contextHandler
        );

        $workflow->execute($context);

        $this->assertEquals('invalid', $context->getCurrentState());
        $this->assertTrue($context->hasFinished());
    }

    public function testSuccessFlow()
    {
        $services = new Container();
        $eventDispatcher = new EventDispatcher();
        $context = new Context('order_workflow');
        $contextHandler = new ContextHandler();

        $services['stock.facade'] = $this->prophesize(StockFacade::class)
            ->checkOrderAvailability($context)->getObjectProphecy()
            ->reserveOrderItems($context)->getObjectProphecy()
            ->reveal();

        $services['test.guard.stock'] = $this->prophesize(GuardStock::class)
            ->isStocked($context)->willReturn(true)->getObjectProphecy()
            ->reveal();

        $services['payment.facade'] = $this->prophesize(PaymentFacade::class)
            ->authorizeOrderPayment($context)->getObjectProphecy()
            ->capturePayment($context)->getObjectProphecy()
            ->reveal();

        $services['test.guard.payment'] = $this->prophesize(GuardPayment::class)
            ->isAuthorized($context)->willReturn(true)->getObjectProphecy()
            ->reveal();

        $services['fraud.facade'] = $this->prophesize(FraudFacade::class)
            ->requestFraudAnalysis($context)->getObjectProphecy()
            ->reveal();

        $services['test.guard.fraud'] = $this->prophesize(GuardFraud::class)
            ->requestSent($context)->willReturn(true)->getObjectProphecy()
            ->isFraud($context)->willReturn(false)->getObjectProphecy()
            ->isNotFraud($context)->willReturn(true)->getObjectProphecy()
            ->reveal();

        $services['order.facade'] = $this->prophesize(OrderFacade::class)
            ->notifyOrderSent($context)->getObjectProphecy()
            ->reveal();

        $workflow = new Workflow(
            $this->definitionsContainer,
            $services,
            $eventDispatcher,
            $contextHandler
        );

        $workflow->execute($context);

        $this->assertEquals('package_process', $context->getCurrentState());
        $this->assertFalse($context->hasFinished());

        $childrenContexts = $context->getChildrenContexts();
        $this->assertEquals('state2', $childrenContexts[0]->getCurrentState());
        $this->assertTrue($childrenContexts[0]->hasFinished());

        $this->assertEquals('state1', $childrenContexts[1]->getCurrentState());
        $this->assertFalse($childrenContexts[1]->hasFinished());

        $this->assertEquals('state1', $childrenContexts[2]->getCurrentState());
        $this->assertFalse($childrenContexts[2]->hasFinished());

        $this->assertEmpty($workflow->getAvailableEvents($context));
        $this->assertEmpty($workflow->getAvailableEvents($childrenContexts[0]));
        $this->assertEquals([ TransitionEvents::ORDER_PACKAGED ], $workflow->getAvailableEvents($childrenContexts[1]));
        $this->assertEquals([ TransitionEvents::ORDER_PACKAGED ], $workflow->getAvailableEvents($childrenContexts[2]));

        // Resume child warehouse_workflow
        $workflow->execute($childrenContexts[2], TransitionEvents::ORDER_PACKAGED);

        $this->assertEquals('package_process', $context->getCurrentState());
        $this->assertFalse($context->hasFinished());

        $this->assertEquals('state1', $childrenContexts[1]->getCurrentState());
        $this->assertFalse($childrenContexts[1]->hasFinished());

        $this->assertEquals('state2', $childrenContexts[2]->getCurrentState());
        $this->assertTrue($childrenContexts[2]->hasFinished());

        $this->assertEmpty($workflow->getAvailableEvents($childrenContexts[2]));
        $this->assertEquals([ TransitionEvents::ORDER_PACKAGED ], $workflow->getAvailableEvents($childrenContexts[1]));

        // Resume child logistics_workflow
        $workflow->execute($childrenContexts[1], TransitionEvents::ORDER_PACKAGED);

        $this->assertEquals('state2', $childrenContexts[1]->getCurrentState());
        $this->assertTrue($childrenContexts[1]->hasFinished());

        $this->assertEquals('sent', $context->getCurrentState());
        $this->assertTrue($context->hasFinished());

        $this->assertEquals([
            'new', 'check_stock_availability', 'authorize_payment', 'request_fraud_check',
            'waiting_fraud_check_response', 'capture_payment', 'package_process', 'sent'
        ], $context->stateHistory);

        $this->assertEquals([
            'state1', 'state2'
        ], $childrenContexts[0]->stateHistory);

        $this->assertEquals([
            'state1', 'state2'
        ], $childrenContexts[1]->stateHistory);

        $this->assertEquals([
            'state1', 'state2'
        ], $childrenContexts[2]->stateHistory);
    }
}
