<?php

declare(strict_types=1);

namespace Noir\At\Tests;

use Noir\At\Wrapper as At;

class TestableAtWrapper extends At
{
    /**
     * @return string
     */
    public static function getQueueRegex(): string
    {
        return static::$queueRegex;
    }
}
