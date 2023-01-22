<?php

namespace Terablaze\Testing;

use Closure;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Throwable;

class TestCase extends BaseTestCase
{
    protected function assertExceptionThrown(Closure $risky, string $exceptionType): array
    {
        $result = null;
        $exception = null;

        try {
            $result = $risky();
            $this->fail('exception was not thrown');
        } catch (Throwable $e) {
            $actualType = get_class($e);

            if ($actualType !== $exceptionType) {
                $this->fail("exception was {$actualType}, but expected {$exceptionType}");
            }

            $exception = $e;
        }

        return [$exception, $result];
    }
}
