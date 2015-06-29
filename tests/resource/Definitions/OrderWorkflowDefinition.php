<?php

namespace BalnoWorkflow\TestResource\Definitions;

use BalnoWorkflow\TestResource\TransitionEvents;

class OrderWorkflowDefinition implements WorkflowDefinitionInterface
{
    const NAME = 'order_workflow';

    public static function getDefinition()
    {
        return [
            'new' => [
                targets => [
                    'check_stock_availability' => null,
                ],
            ],
            'check_stock_availability' => [
                targets => [
                    'authorize_payment' => [ guard => 'stock:isStocked' ],
                    'invalid' => null,
                ],
                onEntry => [
                    [ action => 'stock:checkOrderAvailability' ],
                ],
            ],
            'authorize_payment' => [
                targets => [
                    'request_fraud_check' => [ guard => 'payment:isAuthorized' ],
                    'invalid' => null,
                ],
                onEntry => [
                    [ action => 'payment:authorizeOrderPayment' ],
                ],
            ],
            'request_fraud_check' => [
                targets => [
                    'waiting_fraud_check_response' => [ guard => 'fraud:requestSent' ],
                    'request_fraud_check' => [ guard => 'timer:hasTimedOut("30m")' ],
                ],
                onEntry => [
                    [ action => 'fraud:requestFraudAnalysis' ],
                ],
            ],
            'waiting_fraud_check_response' => [
                targets => [
                    'canceled' => [ guard => 'fraud:isFraud' ],
                    'capture_payment' => [ guard => 'fraud:isNotFraud' ],
                    'clarify_fraud' => [ guard => 'timer:hasTimedOut("1d")' ],
                ],
            ],
            'clarify_fraud' => [
                targets => [
                    'request_fraud_check' => [
                        event => TransitionEvents::RETRY_REQUEST_FRAUD
                    ],
                    'capture_payment' => [
                        event => TransitionEvents::NOT_FRAUD,
                        guard => 'user:isAllowedToExecute'
                    ],
                    'canceled' => [
                        event => TransitionEvents::CANCEL_ORDER
                    ],
                ],
                onEntry => [
                    [ action => 'sac:notifyFraudPending' ],
                ],
            ],
            'capture_payment' => [
                targets => [
                    'package_process' => null,
                ],
                onEntry => [
                    [ action  => 'payment:capturePayment' ],
                    [ action  => 'stock:reserveOrderItems' ],
                ],
            ],
            'package_process' => [
                targets => [
                    'canceled' => [ event => TransitionEvents::CANCEL_ORDER ],
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
                    [ action => 'order:notifyOrderSent' ],
                ],
            ],
            'invalid' => [
                onEntry => [
                    [ action => 'sac:notifyInvalidOrder' ],
                    [ action => 'order:notifyInvalidOrder' ],
                ],
            ],
            'canceled' => [
                onEntry => [
                    [ action => 'order:notifyOrderCanceled' ],
                ],
            ],
        ];
    }
}
