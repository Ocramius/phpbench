<?php

namespace PhpBench\Assertion\Ast;

use PhpBench\Assertion\Ast\Microseconds;
use PhpBench\Assertion\Exception\InvalidTimeUnit;
use PhpBench\Util\TimeUnit;

class TimeValue implements Value
{
    /**
     * @var float
     */
    private $value;
    /**
     * @var string
     */
    private $unit;

    public function __construct(float $value, string $unit)
    {
        $this->value = $value;
        $this->unit = $unit;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function value(): float
    {
        return $this->value;
    }

    public static function fromMicroseconds(float $int): self
    {
        return new self($int, TimeUnit::MICROSECONDS);
    }
}
