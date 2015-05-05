<?php

namespace BalnoWorkflow\IntegrationTests\Interfaces;

use BalnoWorkflow\ContextInterface;

interface FraudFacade
{
    public function requestFraudAnalysis(ContextInterface $context);
}
