<?php
/**
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2017-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Diactoros\Request;

use Psr\Http\Message\RequestInterface;
use Zend\Diactoros\Exception;
use Zend\Diactoros\Request;
use Zend\Diactoros\Stream;

/**
 * Serialize or deserialize request messages to/from arrays.
 *
 * This class provides functionality for serializing a RequestInterface instance
 * to an array, as well as the reverse operation of creating a Request instance
 * from an array representing a message.
 */
final class ArraySerializer
{
    /**
     * Serialize a request message to an array.
     */
    public static function toArray(RequestInterface $request)
    {
        return array(
            'method'           => $request->getMethod(),
            'request_target'   => $request->getRequestTarget(),
            'uri'              => (string) $request->getUri(),
            'protocol_version' => $request->getProtocolVersion(),
            'headers'          => $request->getHeaders(),
            'body'             => (string) $request->getBody(),
        );
    }

    /**
     * Deserialize a request array to a request instance.
     *
     * @throws Exception\DeserializationException when cannot deserialize response
     */
    public static function fromArray(array $serializedRequest)
    {
        try {
            $uri             = self::getValueFromKey($serializedRequest, 'uri');
            $method          = self::getValueFromKey($serializedRequest, 'method');
            $body            = new Stream('php://memory', 'wb+');
            $body->write(self::getValueFromKey($serializedRequest, 'body'));
            $headers         = self::getValueFromKey($serializedRequest, 'headers');
            $requestTarget   = self::getValueFromKey($serializedRequest, 'request_target');
            $protocolVersion = self::getValueFromKey($serializedRequest, 'protocol_version');

            $request = new Request($uri, $method, $body, $headers);

            return $request
                ->withRequestTarget($requestTarget)
                ->withProtocolVersion($protocolVersion);
        } catch (\Exception $exception) {
            throw Exception\DeserializationException::forRequestFromArray($exception);
        }
    }

    /**
     * @return mixed
     * @throws Exception\DeserializationException
     */
    private static function getValueFromKey(array $data, $key, $message = null)
    {
        if (isset($data[$key])) {
            return $data[$key];
        }
        if ($message === null) {
            $message = sprintf('Missing "%s" key in serialized request', $key);
        }
        throw new Exception\DeserializationException($message);
    }
}
