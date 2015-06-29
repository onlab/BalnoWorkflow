<?php

namespace BalnoWorkflow\TestResource;

interface TransitionEvents
{
    const NOT_FRAUD = 'not_fraud';
    const CANCEL_ORDER = 'cancel_order';
    const RETRY_REQUEST_FRAUD = 'retry_request_fraud';
    const ORDER_PACKAGED = 'order_packaged';
    const ORDER_SENT = 'order_sent';
    const RETRY_INVOICE_GENERATION = 'retry_invoice_generation';
}
