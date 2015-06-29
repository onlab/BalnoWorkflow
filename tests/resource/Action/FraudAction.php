<?php

namespace BalnoWorkflow\TestResource\Action;

use BalnoWorkflow\ContextInterface;

interface FraudAction
{
    public function requestFraudAnalysis(ContextInterface $context);
}
