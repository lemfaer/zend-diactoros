<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Diactoros;

use Zend\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class StreamFactory implements StreamFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createStream($content = '')
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, $content);
        rewind($resource);

        return $this->createStreamFromResource($resource);
    }

    /**
     * {@inheritDoc}
     */
    public function createStreamFromFile($file, $mode = 'r')
    {
        return new Stream($file, $mode);
    }

    /**
     * {@inheritDoc}
     */
    public function createStreamFromResource($resource)
    {
        if (! is_resource($resource) || 'stream' !== get_resource_type($resource)) {
            throw new Exception\InvalidArgumentException(
                'Invalid stream provided; must be a stream resource'
            );
        }
        return new Stream($resource);
    }
}
