<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Diactoros;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Implementation of PSR HTTP streams
 */
class Stream implements StreamInterface
{
    /**
     * @var resource|null
     */
    protected $resource;

    /**
     * @var string|resource
     */
    protected $stream;

    /**
     * @param string|resource $stream
     * @param string $mode Mode with which to open stream
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($stream, $mode = 'r')
    {
        $this->setStream($stream, $mode);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        if (! $this->isReadable()) {
            return '';
        }

        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (RuntimeException $e) {
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if (! $this->resource) {
            return;
        }

        $resource = $this->detach();
        fclose($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        return $resource;
    }

    /**
     * Attach a new stream/resource to the instance.
     *
     * @param string|resource $resource
     * @param string $mode
     * @throws Exception\InvalidArgumentException for stream identifier that cannot be
     *     cast to a resource
     * @throws Exception\InvalidArgumentException for non-resource stream
     */
    public function attach($resource, $mode = 'r')
    {
        $this->setStream($resource, $mode);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        if (null === $this->resource) {
            return null;
        }

        $stats = fstat($this->resource);
        if ($stats !== false) {
            return $stats['size'];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function tell()
    {
        if (! $this->resource) {
            throw Exception\UntellableStreamException::dueToMissingResource();
        }

        $result = ftell($this->resource);
        if (! is_int($result)) {
            throw Exception\UntellableStreamException::dueToPhpError();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function eof()
    {
        if (! $this->resource) {
            return true;
        }

        return feof($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable()
    {
        if (! $this->resource) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);
        return $meta['seekable'];
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = \SEEK_SET)
    {
        if (! $this->resource) {
            throw Exception\UnseekableStreamException::dueToMissingResource();
        }

        if (! $this->isSeekable()) {
            throw Exception\UnseekableStreamException::dueToConfiguration();
        }

        $result = fseek($this->resource, $offset, $whence);

        if (0 !== $result) {
            throw Exception\UnseekableStreamException::dueToPhpError();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable()
    {
        if (! $this->resource) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'];

        return (
            strstr($mode, 'x')
            || strstr($mode, 'w')
            || strstr($mode, 'c')
            || strstr($mode, 'a')
            || strstr($mode, '+')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function write($string)
    {
        if (! $this->resource) {
            throw Exception\UnwritableStreamException::dueToMissingResource();
        }

        if (! $this->isWritable()) {
            throw Exception\UnwritableStreamException::dueToConfiguration();
        }

        $result = fwrite($this->resource, $string);

        if (false === $result) {
            throw Exception\UnwritableStreamException::dueToPhpError();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable()
    {
        if (! $this->resource) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'];

        return (strstr($mode, 'r') || strstr($mode, '+'));
    }

    /**
     * {@inheritdoc}
     */
    public function read($length)
    {
        if (! $this->resource) {
            throw Exception\UnreadableStreamException::dueToMissingResource();
        }

        if (! $this->isReadable()) {
            throw Exception\UnreadableStreamException::dueToConfiguration();
        }

        $result = fread($this->resource, $length);

        if (false === $result) {
            throw Exception\UnreadableStreamException::dueToPhpError();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents()
    {
        if (! $this->isReadable()) {
            throw Exception\UnreadableStreamException::dueToConfiguration();
        }

        $result = stream_get_contents($this->resource);
        if (false === $result) {
            throw Exception\UnreadableStreamException::dueToPhpError();
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($key = null)
    {
        if (null === $key) {
            return stream_get_meta_data($this->resource);
        }

        $metadata = stream_get_meta_data($this->resource);
        if (! array_key_exists($key, $metadata)) {
            return null;
        }

        return $metadata[$key];
    }

    /**
     * Set the internal stream resource.
     *
     * @param string|resource $stream String stream target or stream resource.
     * @param string $mode Resource mode for stream target.
     * @throws Exception\InvalidArgumentException for invalid streams or resources.
     */
    private function setStream($stream, $mode = 'r')
    {
        $error    = null;
        $resource = $stream;

        if (is_string($stream)) {
            set_error_handler(function ($e) use (&$error) {
                if ($e !== \E_WARNING) {
                    return;
                }

                $error = $e;
            });
            $resource = fopen($stream, $mode);
            restore_error_handler();
        }

        if ($error) {
            throw new Exception\InvalidArgumentException('Invalid stream reference provided');
        }

        if (! is_resource($resource) || 'stream' !== get_resource_type($resource)) {
            throw new Exception\InvalidArgumentException(
                'Invalid stream provided; must be a string stream identifier or stream resource'
            );
        }

        if ($stream !== $resource) {
            $this->stream = $stream;
        }

        $this->resource = $resource;
    }
}
