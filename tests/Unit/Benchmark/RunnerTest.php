<?php

/*
 * This file is part of the PHP Bench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Tests\Benchmark;

use PhpBench\Benchmark\Runner;
use PhpBench\Benchmark;
use PhpBench\Benchmark\Iteration;

class RunnerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->logger = $this->prophesize('PhpBench\\ProgressLogger');
        $this->collectionBuilder = $this->prophesize('PhpBench\\Benchmark\\CollectionBuilder');
        $this->subjectBuilder = $this->prophesize('PhpBench\\Benchmark\\SubjectBuilder');
        $this->case = new RunnerTestBenchCase();
        $this->collection = $this->prophesize('PhpBench\\Benchmark\\Collection');
        $this->subject = $this->prophesize('PhpBench\\Benchmark\\Subject');

        $this->runner = new Runner(
            $this->collectionBuilder->reveal(),
            $this->subjectBuilder->reveal(),
            $this->logger->reveal()
        );
    }

    /**
     * It should run the tests.
     */
    public function testRunner()
    {
        $iterations = 1;

        $this->collectionBuilder->buildCollection()->willReturn($this->collection);
        $this->collection->getBenchmarks()->willReturn(array(
            $this->case,
        ));
        $this->subjectBuilder->buildSubjects($this->case)->willReturn(array(
            $this->subject->reveal(),
        ));
        $this->subject->getNbIterations()->willReturn($iterations);
        $this->subject->getParameterProviders()->willReturn(array(
            'paramSetOne',
            'paramSetTwo',
        ));
        $this->subject->getMethodName()->willReturn('benchFoo');
        $this->subject->getBeforeMethods()->willReturn(array('beforeFoo'));
        $this->subject->getDescription()->willReturn('Hello world');

        $result = $this->runner->runAll();

        $this->assertTrue($this->case->called);
        $this->assertTrue($this->case->beforeCalled);

        $this->assertInstanceOf('PhpBench\\Result\\SuiteResult', $result);
        $this->assertEquals(1, count($result->getBenchmarkResults()));
    }
}

class RunnerTestBenchCase implements Benchmark
{
    public $called = false;
    public $beforeCalled = false;

    public function paramSetOne()
    {
        return array(
            array('foo' => 'bar'),
            array('foo' => 'bar'),
        );
    }

    public function beforeFoo(Iteration $iteration)
    {
        $this->beforeCalled = true;
    }

    public function paramSetTwo()
    {
        return array(
            array('bar' => 'foo'),
        );
    }

    public function benchFoo(Iteration $iteration)
    {
        $this->called = true;
    }
}
