<?php

namespace BalnoWorkflow\IntegrationTests;

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
     * @var \ArrayAccess
     */
    protected $definitionsContainer;

    /**
     * @var array
     */
    protected $workflowHistory;


    public function testOutOfStockFlow()
    {
        $services = new \ArrayObject();
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
        $services = new \ArrayObject();
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
