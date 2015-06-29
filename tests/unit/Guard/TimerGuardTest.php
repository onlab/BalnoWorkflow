<?php

namespace BalnoWorkflow\Guard\UnitTest;

use BalnoWorkflow\ContextInterface;
use BalnoWorkflow\Guard\TimerGuard;

class TimerGuardTest extends \PHPUnit_Framework_TestCase
{
    public function testSimpleTimedOutStatus()
    {
        $context = $this->prophesize(ContextInterface::class)
            ->getCurrentState()->willReturn('test')->getObjectProphecy()
            ->getLastStateChangedAt()->willReturn(new \DateTime("-3 min"))->getObjectProphecy()
            ->getStateHistory()->willReturn([
                'test'
            ])->getObjectProphecy()
            ->reveal();

        $retryGuard = new TimerGuard();
        $result = $retryGuard->hasTimedOut($context, "+3 min");
        $this->assertTrue($result);

        $result = $retryGuard->hasTimedOut($context, "+178 sec");
        $this->assertTrue($result);

        $result = $retryGuard->hasTimedOut($context, "+182 sec");
        $this->assertFalse($result);
    }

    public function testTimeoutUsingHistoricalData()
    {
        $context = $this->prophesize(ContextInterface::class)
            ->getCurrentState()->willReturn('test')->getObjectProphecy()
            ->getLastStateChangedAt()->willReturn(new \DateTime("-90 min"))->getObjectProphecy()
            ->getStateHistory()->willReturn([
                'test', 'test', 'test'
            ])->getObjectProphecy()
            ->reveal();

        $retryGuard = new TimerGuard();
        $result = $retryGuard->hasTimedOut($context, "+1 day", "+15 hour", "+90 min", "+15 hour");
        $this->assertTrue($result);

        $result = $retryGuard->hasTimedOut($context, "+1 day", "+15 hour", "+91 min", "+10 min");
        $this->assertFalse($result);

        $retryGuard = new TimerGuard();
        $result = $retryGuard->hasTimedOut($context, "+1 day", "+15 hour", "+90 min");
        $this->assertTrue($result);

        $result = $retryGuard->hasTimedOut($context, "+1 day", "+15 hour", "+91 min");
        $this->assertFalse($result);

        $retryGuard = new TimerGuard();
        $result = $retryGuard->hasTimedOut($context, "+1 day", "+90 min");
        $this->assertTrue($result);

        $result = $retryGuard->hasTimedOut($context, "+1 day", "+91 min");
        $this->assertFalse($result);

        $retryGuard = new TimerGuard();
        $result = $retryGuard->hasTimedOut($context, "+90 min");
        $this->assertTrue($result);

        $result = $retryGuard->hasTimedOut($context, "+91 min");
        $this->assertFalse($result);
    }
}
