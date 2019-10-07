<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Diactoros\Exception;

use RuntimeException;

class UnwritableStreamException extends RuntimeException implements ExceptionInterface
{
    public static function dueToConfiguration()
    {
        return new self('Stream is not writable');
    }

    public static function dueToMissingResource()
    {
        return new self('No resource available; cannot write');
    }

    public static function dueToPhpError()
    {
        return new self('Error writing to stream');
    }

    public static function forCallbackStream()
    {
        return new self('Callback streams cannot write');
    }
}
