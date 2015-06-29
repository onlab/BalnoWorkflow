<?php

namespace BalnoWorkflow\Guard\UnitTest;

use BalnoWorkflow\ContextInterface;
use BalnoWorkflow\Guard\HistoryGuard;

class HistoryGuardTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContextInterface
     */
    protected $context;

    public function setUp()
    {
        $this->context = $this->prophesize(ContextInterface::class)
            ->getCurrentState()->willReturn('test')->getObjectProphecy()
            ->getStateHistory()->willReturn([
                'out', 'test', 'out', 'test', 'test'
            ])->getObjectProphecy()
            ->reveal();
    }

    /**
     * @return array
     */
    public function entryConditions()
    {
        return [
            [ '=', '3', true ],
            [ '=', '2', false ],

            [ '==', '3', true ],
            [ '==', '2', false ],

            [ '!=', '3', false ],
            [ '!=', '2', true ],

            [ '>=', '4', false ],
            [ '>=', '3', true ],
            [ '>=', '2', true ],

            [ '>', '4', false ],
            [ '>', '3', false ],
            [ '>', '2', true ],

            [ '<=', '4', true ],
            [ '<=', '3', true ],
            [ '<=', '2', false ],

            [ '<', '4', true ],
            [ '<', '3', false ],
            [ '<', '2', false ],
        ];
    }

    /**
     * @dataProvider entryConditions
     */
    public function testStatusEntryConditionsRespected($condition, $value, $expected)
    {
        $historyGuard = new HistoryGuard();

        $result = $historyGuard->statusEntries($this->context, $condition, $value);
        $this->assertEquals($expected, $result);
    }

    public function testStatusReentryRespected()
    {
        $historyGuard = new HistoryGuard();

        $context = $this->prophesize(ContextInterface::class)
            ->getCurrentState()->willReturn('test')->getObjectProphecy()
            ->getStateHistory()->willReturn([
                'out', 'test', 'out', 'test', 'test', 'test'
            ])->getObjectProphecy()
            ->reveal();

        $result = $historyGuard->statusReentries($context, '=', 2);
        $this->assertTrue($result);


        $context = $this->prophesize(ContextInterface::class)
            ->getCurrentState()->willReturn('test')->getObjectProphecy()
            ->getStateHistory()->willReturn([
                'out', 'test', 'out', 'test'
            ])->getObjectProphecy()
            ->reveal();

        $result = $historyGuard->statusReentries($context, '=', 0);
        $this->assertTrue($result);
    }
}
