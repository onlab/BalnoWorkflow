<?php

namespace BalnoWorkflow\UnitTest\Resources;

use BalnoWorkflow\IntegrationTests\Interfaces\TransitionEvents;

const targets = 'targets';
const guard = 'guard';
const action = 'action';
const event = 'event';
const type = 'type';
const onEntry = 'onEntry';
const onExit = 'onExit';
const parallel = 'parallel';

class WorkflowDefinitionContainer
{
    /**
     * @return \ArrayObject
     */
    public static function getTestDefinitions()
    {
        $definitionsContainer = new \ArrayObject();
        $definitionsContainer['order_workflow'] = self::getOrderWorkflow();
        $definitionsContainer['invoice_workflow'] = self::getParallelWorkflowNonStop();
        $definitionsContainer['warehouse_workflow'] = self::getParallelWorkflowRequireEvent();
        $definitionsContainer['logistics_workflow'] = self::getParallelWorkflowRequireEvent();

        return $definitionsContainer;
    }

    /**
     * @return array
     */
    protected static function getOrderWorkflow()
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

    /**
     * @return array
     */
    protected static function getParallelWorkflowNonStop()
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

    /**
     * @return array
     */
    protected static function getParallelWorkflowRequireEvent()
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
}
