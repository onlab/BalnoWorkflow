<?php

namespace BalnoWorkflow\FunctionalTest;

use BalnoWorkflow\TestResource\Action\FraudAction;
use BalnoWorkflow\TestResource\Action\InvoiceAction;
use BalnoWorkflow\TestResource\Action\OrderAction;
use BalnoWorkflow\TestResource\Action\PaymentAction;
use BalnoWorkflow\TestResource\Action\SacAction;
use BalnoWorkflow\TestResource\Action\ShippingAction;
use BalnoWorkflow\TestResource\Action\StockAction;
use BalnoWorkflow\TestResource\Context;
use BalnoWorkflow\TestResource\Definitions\OrderWorkflowDefinition;
use BalnoWorkflow\TestResource\Guard\FraudGuard;
use BalnoWorkflow\TestResource\Guard\InvoiceGuard;
use BalnoWorkflow\TestResource\Guard\PaymentGuard;
use BalnoWorkflow\TestResource\Guard\ShippingGuard;
use BalnoWorkflow\TestResource\Guard\StockGuard;
use BalnoWorkflow\TestResource\Handler\ContextHandler;
use BalnoWorkflow\TestResource\TransitionEvents;
use BalnoWorkflow\TestResource\WorkflowDefinitionContainer;
use BalnoWorkflow\Workflow;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcher;

class WorkflowTest extends \PHPUnit_Framework_TestCase
{
    public function testOutOfStockFlow()
    {
        $eventDispatcher = new EventDispatcher();

        $actions = new \ArrayObject();
        $guards = new \ArrayObject();

        $context = new Context(OrderWorkflowDefinition::NAME);
        $contextHandler = new ContextHandler();

        $guards['stock'] = $this->prophesize(StockGuard::class)
            ->isStocked($context)->willReturn(false)->getObjectProphecy()
            ->reveal();

        $guards['payment'] = $this->prophesize(PaymentGuard::class)
            ->isAuthorized($context)->willreturn(true)->getObjectProphecy()
            ->reveal();

        $actions['stock'] = $this->prophesize(StockAction::class)
            ->checkOrderAvailability($context)->getObjectProphecy()
            ->reveal();

        $actions['sac'] = $this->prophesize(SacAction::class)
            ->notifyInvalidOrder($context)->getObjectProphecy()
            ->reveal();

        $actions['order'] = $this->prophesize(OrderAction::class)
            ->notifyInvalidOrder($context)->getObjectProphecy()
            ->reveal();

        $workflow = new Workflow(
            WorkflowDefinitionContainer::getTestDefinitions(),
            $guards,
            $actions,
            $eventDispatcher,
            $contextHandler
        );

        $workflow->execute($context);

        $this->assertEquals('invalid', $context->getCurrentState());
        $this->assertTrue($context->hasFinished());
    }

    public function testSuccessFlow()
    {
        $eventDispatcher = new EventDispatcher();
        $actions = new \ArrayObject();
        $guards = new \ArrayObject();

        $context = new Context(OrderWorkflowDefinition::NAME);
        $contextHandler = new ContextHandler();

        $actions['stock'] = $this->prophesize(StockAction::class)
            ->checkOrderAvailability($context)->getObjectProphecy()
            ->reserveOrderItems($context)->getObjectProphecy()
            ->reveal();

        $actions['payment'] = $this->prophesize(PaymentAction::class)
            ->authorizeOrderPayment($context)->getObjectProphecy()
            ->capturePayment($context)->getObjectProphecy()
            ->reveal();

        $actions['fraud'] = $this->prophesize(FraudAction::class)
            ->requestFraudAnalysis($context)->getObjectProphecy()
            ->reveal();

        $actions['order'] = $this->prophesize(OrderAction::class)
            ->notifyOrderSent($context)->getObjectProphecy()
            ->reveal();

        $actions['invoice'] = $this->prophesize(InvoiceAction::class)
            ->generateInvoice($context)->getObjectProphecy()
            ->reveal();

        $actions['shipping'] = $this->prophesize(ShippingAction::class)
            ->quote(Argument::type(Context::class))->getObjectProphecy()
            ->reveal();

        $guards['stock'] = $this->prophesize(StockGuard::class)
            ->isStocked($context)->willReturn(true)->getObjectProphecy()
            ->reveal();

        $guards['payment'] = $this->prophesize(PaymentGuard::class)
            ->isAuthorized($context)->willReturn(true)->getObjectProphecy()
            ->reveal();

        $guards['fraud'] = $this->prophesize(FraudGuard::class)
            ->requestSent($context)->willReturn(true)->getObjectProphecy()
            ->isFraud($context)->willReturn(false)->getObjectProphecy()
            ->isNotFraud($context)->willReturn(true)->getObjectProphecy()
            ->reveal();

        $guards['invoice'] = $this->prophesize(InvoiceGuard::class)
            ->invoiceCreated(Argument::type(Context::class))->willReturn(true)->getObjectProphecy()
            ->reveal();

        $guards['shipping'] = $this->prophesize(ShippingGuard::class)
            ->hasNoCarrierAvailable(Argument::type(Context::class))->willReturn(false)->getObjectProphecy()
            ->reveal();

        $workflow = new Workflow(
            WorkflowDefinitionContainer::getTestDefinitions(),
            $guards,
            $actions,
            $eventDispatcher,
            $contextHandler
        );

        $workflow->execute($context);

        $this->assertEquals('package_process', $context->getCurrentState());
        $this->assertFalse($context->hasFinished());

        $childrenContexts = $context->getChildrenContexts();
        $this->assertEquals('invoice_generated', $childrenContexts[0]->getCurrentState());
        $this->assertTrue($childrenContexts[0]->hasFinished());

        $this->assertEquals('waiting_order_packaging', $childrenContexts[1]->getCurrentState());
        $this->assertFalse($childrenContexts[1]->hasFinished());

        $this->assertEquals('waiting_carrier', $childrenContexts[2]->getCurrentState());
        $this->assertFalse($childrenContexts[2]->hasFinished());

        $this->assertEquals(['cancel_order', 'order_packaged', 'order_sent'], array_values($workflow->getAvailableEvents($context)));

        // Resume child warehouse_workflow
        $workflow->execute($context, TransitionEvents::ORDER_PACKAGED);

        $this->assertEquals('package_process', $context->getCurrentState());
        $this->assertFalse($context->hasFinished());

        $this->assertEquals('order_packaged', $childrenContexts[1]->getCurrentState());
        $this->assertTrue($childrenContexts[1]->hasFinished());

        $this->assertEquals('waiting_carrier', $childrenContexts[2]->getCurrentState());
        $this->assertFalse($childrenContexts[2]->hasFinished());

        $this->assertEquals(['cancel_order', 'order_sent'], array_values($workflow->getAvailableEvents($context)));

        // Resume child logistics_workflow
        $workflow->execute($context, TransitionEvents::ORDER_SENT);

        $this->assertEquals('order_sent', $childrenContexts[2]->getCurrentState());
        $this->assertTrue($childrenContexts[2]->hasFinished());

        $this->assertEquals([
            'new', 'check_stock_availability', 'authorize_payment', 'request_fraud_check',
            'waiting_fraud_check_response', 'capture_payment', 'package_process', 'sent'
        ], $context->getStateHistory());

        $this->assertEquals([
            'generating_invoice', 'waiting_invoice_generation', 'invoice_generated'
        ], $childrenContexts[0]->getStateHistory());

        $this->assertEquals([
            'waiting_order_packaging', 'order_packaged'
        ], $childrenContexts[1]->getStateHistory());

        $this->assertEquals([
            'quoting_shipment', 'waiting_carrier', 'order_sent'
        ], $childrenContexts[2]->getStateHistory());
    }

    public function testRaisingEventCancelOrder()
    {
        $eventDispatcher = new EventDispatcher();
        $actions = new \ArrayObject();
        $guards = new \ArrayObject();

        $context = new Context(OrderWorkflowDefinition::NAME);
        $contextHandler = new ContextHandler();

        $actions['stock'] = $this->prophesize(StockAction::class)
            ->checkOrderAvailability($context)->getObjectProphecy()
            ->reserveOrderItems($context)->getObjectProphecy()
            ->reveal();

        $actions['payment'] = $this->prophesize(PaymentAction::class)
            ->authorizeOrderPayment($context)->getObjectProphecy()
            ->capturePayment($context)->getObjectProphecy()
            ->reveal();

        $actions['fraud'] = $this->prophesize(FraudAction::class)
            ->requestFraudAnalysis($context)->getObjectProphecy()
            ->reveal();

        $actions['order'] = $this->prophesize(OrderAction::class)
            ->notifyOrderSent($context)->getObjectProphecy()
            ->reveal();

        $actions['invoice'] = $this->prophesize(InvoiceAction::class)
            ->generateInvoice($context)->getObjectProphecy()
            ->reveal();

        $actions['shipping'] = $this->prophesize(ShippingAction::class)
            ->quote(Argument::type(Context::class))->getObjectProphecy()
            ->reveal();

        $guards['stock'] = $this->prophesize(StockGuard::class)
            ->isStocked($context)->willReturn(true)->getObjectProphecy()
            ->reveal();

        $guards['payment'] = $this->prophesize(PaymentGuard::class)
            ->isAuthorized($context)->willReturn(true)->getObjectProphecy()
            ->reveal();

        $guards['fraud'] = $this->prophesize(FraudGuard::class)
            ->requestSent($context)->willReturn(true)->getObjectProphecy()
            ->isFraud($context)->willReturn(false)->getObjectProphecy()
            ->isNotFraud($context)->willReturn(true)->getObjectProphecy()
            ->reveal();

        $guards['invoice'] = $this->prophesize(InvoiceGuard::class)
            ->invoiceCreated(Argument::type(Context::class))->willReturn(true)->getObjectProphecy()
            ->reveal();

        $guards['shipping'] = $this->prophesize(ShippingGuard::class)
            ->hasNoCarrierAvailable(Argument::type(Context::class))->willReturn(true)->getObjectProphecy()
            ->reveal();

        $workflow = new Workflow(
            WorkflowDefinitionContainer::getTestDefinitions(),
            $guards,
            $actions,
            $eventDispatcher,
            $contextHandler
        );

        $workflow->execute($context);

        $this->assertEquals('canceled', $context->getCurrentState());
        $this->assertTrue($context->hasFinished());
    }
}
