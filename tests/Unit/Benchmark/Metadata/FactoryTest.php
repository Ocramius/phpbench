<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Tests\Unit\Benchmark\Metadata;

use PhpBench\Benchmark\Metadata\BenchmarkMetadata;
use PhpBench\Benchmark\Metadata\DriverInterface;
use PhpBench\Benchmark\Metadata\Factory;
use PhpBench\Benchmark\Metadata\SubjectMetadata;
use PhpBench\Tests\Util\TestUtil;
use PhpBench\Reflection\FileReflectorInterface;
use BetterReflection\Reflection\ReflectionClass;
use BetterReflection\Reflection\ReflectionMethod;
use Prophecy\Argument;

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    const FNAME = 'fname';
    const PATH = '/path/to';

    private $factory;

    public function setUp()
    {
        $this->reflector = $this->prophesize(FileReflectorInterface::class);
        $this->driver = $this->prophesize(DriverInterface::class);
        $this->factory = new Factory(
            $this->reflector->reveal(),
            $this->driver->reveal()
        );


        $this->reflectionMethod = $this->prophesize(ReflectionMethod::class);
        $this->reflectionMethod2 = $this->prophesize(ReflectionMethod::class);
        $this->reflectionClass = $this->prophesize(ReflectionClass::class);
        $this->benchmarkMetadata = $this->prophesize(BenchmarkMetadata::class);
        $this->subjectMetadata = $this->prophesize(SubjectMetadata::class);

    }

    /**
     * It can retrieve the benchmarkMetadata for a file containing a class.
     */
    public function testGetMetadataForFile()
    {
        $this->reflector->reflectFile(self::FNAME)->willReturn($this->reflectionClass->reveal());
        $this->driver->getMetadataForClass($this->reflectionClass->reveal())->willReturn($this->benchmarkMetadata->reveal());
        $this->benchmarkMetadata->getSubjects()->willReturn([]);
        TestUtil::configureBenchmarkMetadata($this->benchmarkMetadata);
        $benchmarkMetadata = $this->factory->getMetadataForFile(self::FNAME);
        $this->assertInstanceOf('PhpBench\Benchmark\Metadata\BenchmarkMetadata', $benchmarkMetadata);
    }

    /**
     * It will return a benchmark populated with subjects.
     */
    public function testWithSubjects()
    {
        $this->reflector->reflectFile(self::FNAME)->willReturn($this->reflectionClass->reveal());
        $this->driver->getMetadataForClass($this->reflectionClass->reveal())->willReturn($this->benchmarkMetadata->reveal());
        $this->benchmarkMetadata->getSubjects()->willReturn([
            $this->subjectMetadata->reveal(),
        ]);
        TestUtil::configureBenchmarkMetadata($this->benchmarkMetadata, [
            'path' => self::PATH,
        ]);
        TestUtil::configureSubjectMetadata($this->subjectMetadata);
        $this->subjectMetadata->setParameterSets([])->shouldBeCalled();

        $benchmarkMetadata = $this->factory->getMetadataForFile(self::FNAME);
        $this->assertInstanceOf('PhpBench\Benchmark\Metadata\BenchmarkMetadata', $benchmarkMetadata);
        $this->assertInternalType('array', $benchmarkMetadata->getSubjects());
        $this->assertCount(1, $benchmarkMetadata->getSubjects());
    }

    /**
     * It should throw an exception if a before/after method does not exist on the benchmark.
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Unknown before
     */
    public function testValidationBeforeMethodsBenchmark()
    {
        $this->reflector->reflectFile(self::FNAME)->willReturn($this->reflectionClass->reveal());
        $this->driver->getMetadataForClass($this->reflectionClass->reveal())->willReturn($this->benchmarkMetadata->reveal());
        TestUtil::configureBenchmarkMetadata($this->benchmarkMetadata, [
            'beforeClassMethods' => ['beforeMe'],
        ]);

        $this->reflectionClass->isAbstract()->willReturn(false);
        $this->reflectionClass->hasMethod('beforeMe')->willReturn(false);
        $this->reflectionClass->getName()->willReturn('hello');

        $this->factory->getMetadataForFile(self::FNAME);
    }

    /**
     * It should throw an exception if a before class method is not static.
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage must be static in benchmark class "TestClass"
     */
    public function testValidationBeforeClassMethodsBenchmarkNotStatic()
    {
        $this->reflector->reflectFile(self::FNAME)->willReturn($this->reflectionClass->reveal());
        $this->driver->getMetadataForClass($this->reflectionClass->reveal())->willReturn($this->benchmarkMetadata->reveal());
        TestUtil::configureBenchmarkMetadata($this->benchmarkMetadata, [
            'beforeClassMethods' => ['beforeMe'],
        ]);

        $this->reflectionClass->isAbstract()->willReturn(false);
        $this->reflectionClass->getName()->willReturn('TestClass');
        $this->reflectionClass->hasMethod('beforeMe')->willReturn(true);
        $this->reflectionClass->getMethod('beforeMe')->willReturn(
            $this->reflectionMethod->reveal()
        );
        $this->reflectionMethod->isStatic()->willReturn(false);

        $this->factory->getMetadataForFile(self::FNAME);
    }

    /**
     * It should throw an exception if a before method IS static.
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage before method "beforeMe" must not be static in benchmark class "TestClass"
     */
    public function testValidationBeforeMethodsBenchmarkIsStatic()
    {
        $this->reflector->reflectFile(self::FNAME)->willReturn($this->reflectionClass->reveal());
        $this->driver->getMetadataForClass($this->reflectionClass->reveal())->willReturn($this->benchmarkMetadata->reveal());

        $this->benchmarkMetadata->getSubjects()->willReturn([
            $this->subjectMetadata->reveal(),
        ]);
        TestUtil::configureBenchmarkMetadata($this->benchmarkMetadata, []);
        TestUtil::configureSubjectMetadata($this->subjectMetadata, [
            'beforeMethods' => ['beforeMe'],
        ]);

        $this->reflectionClass->isAbstract()->willReturn(false);
        $this->reflectionClass->getName()->willReturn('TestClass');
        $this->reflectionClass->hasMethod('beforeMe')->willReturn(true);
        $this->reflectionClass->getMethod('beforeMe')->willReturn(
            $this->reflectionMethod->reveal()
        );
        $this->reflectionMethod->isStatic()->willReturn(true);

        $this->factory->getMetadataForFile(self::FNAME);
    }

    /**
     * It should throw an exception if a before/after method does not exist on the subject.
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Unknown before method "beforeMe" in benchmark class "TestClass"
     */
    public function testValidationBeforeMethodsSubject()
    {
        $this->reflector->reflectFile(self::FNAME)->willReturn($this->reflectionClass->reveal());
        $this->driver->getMetadataForClass($this->reflectionClass->reveal())->willReturn($this->benchmarkMetadata->reveal());

        $this->benchmarkMetadata->getSubjects()->willReturn([
            $this->subjectMetadata->reveal(),
        ]);
        TestUtil::configureBenchmarkMetadata($this->benchmarkMetadata, []);
        TestUtil::configureSubjectMetadata($this->subjectMetadata, [
            'beforeMethods' => ['beforeMe'],
        ]);

        $this->reflectionClass->isAbstract()->willReturn(false);
        $this->reflectionClass->getName()->willReturn('TestClass');
        $this->reflectionClass->hasMethod('beforeMe')->willReturn(false);

        $this->factory->getMetadataForFile(self::FNAME);
    }

    /**
     * It should throw an exception if an after method does not exist.
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Unknown after
     */
    public function testValidationAfterMethods()
    {
        $this->reflector->reflectFile(self::FNAME)->willReturn($this->reflectionClass->reveal());
        $this->driver->getMetadataForClass($this->reflectionClass->reveal())->willReturn($this->benchmarkMetadata->reveal());

        $this->benchmarkMetadata->getSubjects()->willReturn([
            $this->subjectMetadata->reveal(),
        ]);
        TestUtil::configureBenchmarkMetadata($this->benchmarkMetadata, []);
        TestUtil::configureSubjectMetadata($this->subjectMetadata, [
            'afterMethods' => ['beforeMe'],
        ]);

        $this->reflectionClass->isAbstract()->willReturn(false);
        $this->reflectionClass->getName()->willReturn('TestClass');
        $this->reflectionClass->hasMethod('beforeMe')->willReturn(false);

        $this->factory->getMetadataForFile(self::FNAME);
    }

    /**
     * It should return null if the class is not found
     */
    public function testEmptyClassHierachy()
    {
        $this->reflector->reflectFile(self::FNAME)->willReturn(null);

        $this->factory->getMetadataForFile(self::FNAME);
    }

    /**
     * @dataProvider provideInvalidParameters
     */
    public function testInvalidParameters($bodyCode, $expectedMessage)
    {
        $this->setUpParameterTest();
        $this->setExpectedException(\InvalidArgumentException::class, $expectedMessage);

        $this->reflectionMethod2->getBodyCode()->willReturn($bodyCode);

        $this->factory->getMetadataForFile(self::FNAME);
    }

    public function provideInvalidParameters()
    {
        return [
            [
                '[];',
                'Each parameter set must be an array, got "NULL"',
            ],
            [
                'return [ new \stdClass ];',
                'Each parameter group must be an array, got "object" for Benchmark::benchFoo',
            ],
            [
                'return [ [ "one" => new \stdClass ]];',
                'Only scalar values allowed as parameter values, got "object" in Benchmark:benchFoo',
            ]
        ];
    }

    /**
     * @dataProvider provideValidParameters
     */
    public function testValidParameters($bodyCode, $expectedParams)
    {
        $this->setUpParameterTest();
        $this->reflectionMethod2->getBodyCode()->willReturn($bodyCode);
        $this->subjectMetadata->setParameterSets($expectedParams)->shouldBeCalled();

        $this->factory->getMetadataForFile(self::FNAME);
    }

    public function provideValidParameters()
    {
        return [
            // valid
            [
                'return [];',
                [[]],
            ],
            [
                'return [ [ "one" => "two"] ];',
                [ [ [ 'one' => 'two' ] ] ]
            ],
            [
                'return [ [ "foo" => "bar", "bar" => "foo" ], [ "bar" => "boo", "boo" => "bar" ] ];',
                [ [ [ "foo" => "bar", "bar" => "foo" ], [ "bar" => "boo", "boo" => "bar" ] ] ]
            ],
        ];
    }

    private function setUpParameterTest()
    {
        $this->reflector->reflectFile(self::FNAME)->willReturn($this->reflectionClass->reveal());
        $this->driver->getMetadataForClass($this->reflectionClass->reveal())->willReturn($this->benchmarkMetadata->reveal());
        $this->benchmarkMetadata->getSubjects()->willReturn([
            $this->subjectMetadata->reveal(),
        ]);

        TestUtil::configureBenchmarkMetadata($this->benchmarkMetadata, [
            'path' => self::PATH,
        ]);

        TestUtil::configureSubjectMetadata($this->subjectMetadata, [
            'paramProviders' => [ 'provideFoo' ],
        ]);
        $this->reflectionClass->getMethod('beforeMe')->willReturn(
            $this->reflectionMethod->reveal()
        );
        $this->reflectionClass->getMethod('provideFoo')->willReturn(
            $this->reflectionMethod2->reveal()
        );

        $this->reflectionClass->isAbstract()->willReturn(false);
        $this->reflectionClass->hasMethod('beforeMe')->willReturn(true);
    }
}
