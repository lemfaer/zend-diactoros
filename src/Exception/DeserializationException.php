<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Diactoros\Exception;

use Exception;
use UnexpectedValueException;

class DeserializationException extends UnexpectedValueException implements ExceptionInterface
{
    public static function forInvalidHeader()
    {
        throw new self('Invalid header detected');
    }

    public static function forInvalidHeaderContinuation()
    {
        throw new self('Invalid header continuation');
    }

    public static function forRequestFromArray(Exception $previous)
    {
        return new self('Cannot deserialize request', $previous->getCode(), $previous);
    }

    public static function forResponseFromArray(Exception $previous)
    {
        return new self('Cannot deserialize response', $previous->getCode(), $previous);
    }

    public static function forUnexpectedCarriageReturn()
    {
        throw new self('Unexpected carriage return detected');
    }

    public static function forUnexpectedEndOfHeaders()
    {
        throw new self('Unexpected end of headers');
    }

    public static function forUnexpectedLineFeed()
    {
        throw new self('Unexpected line feed detected');
    }
}
