<?php

namespace BalnoWorkflow\Guard;

use BalnoWorkflow\ContextInterface;

trait StatusHistoryAware
{
    /**
     * @param ContextInterface $context
     * @return int
     */
    protected function getStatusSets(ContextInterface $context, $onlyContinuous = true)
    {
        $statusHistory = $context->getStateHistory();
        $historySize = count($statusHistory);
        $count = 1;

        if ($onlyContinuous) {
            while (++$count <= $historySize && $statusHistory[$historySize - $count] === $context->getCurrentState()) ;
        } else {
            foreach ($statusHistory as $status) {
                if ($status === $context->getCurrentState()) {
                    $count++;
                }
            }
        }

        return $count - 1;
    }
}
