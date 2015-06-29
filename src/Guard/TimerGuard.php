<?php

namespace BalnoWorkflow\Guard;

use BalnoWorkflow\ContextInterface;

class TimerGuard
{
    use StatusHistoryAware;

    /**
     * @param ContextInterface $context
     * @param string ...$timeoutIntervalSpec
     * @return bool
     */
    public function hasTimedOut(ContextInterface $context, $timeoutIntervalSpec)
    {
        if (func_num_args() > 2) {
            $timerToUse = $this->getStatusSets($context, false);
            $timeoutIntervalSpec = func_get_arg(min(func_num_args() - 1, $timerToUse));
        }

        $timeoutExpiresAt = clone $context->getLastStateChangedAt();
        $timeoutExpiresAt->modify($timeoutIntervalSpec);

        $timezone = $timeoutExpiresAt->getTimezone();
        $now = new \DateTime('now', $timezone);

        return $timeoutExpiresAt <= $now;
    }
}
