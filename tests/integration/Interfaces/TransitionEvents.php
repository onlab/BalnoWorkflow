<?php

namespace BalnoWorkflow\IntegrationTests\Interfaces;

interface TransitionEvents
{
    const NOT_FRAUD = 'not_fraud';
    const CANCEL = 'cancel';
    const RETRY_REQUEST_FRAUD = 'retry_request_fraud';
    const ORDER_PACKAGED = 'order_packaged';
}
