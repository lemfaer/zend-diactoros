<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Diactoros;

use PHPUnit_Framework_TestCase as TestCase;
use RuntimeException;
use Zend\Diactoros\CallbackStream;

/**
 * @covers \Zend\Diactoros\CallbackStream
 */
class CallbackStreamTest extends TestCase
{
    /**
     * Sample callback to use with testing.
     *
     * @return string
     */
    public function sampleCallback()
    {
        return __METHOD__;
    }

    /**
     * Sample static callback to use with testing.
     *
     * @return string
     */
    public static function sampleStaticCallback()
    {
        return __METHOD__;
    }

    public function testToString()
    {
        $stream = new CallbackStream(function () {
            return 'foobarbaz';
        });

        $ret = $stream->__toString();
        $this->assertSame('foobarbaz', $ret);
    }

    public function testClose()
    {
        $stream = new CallbackStream(function () {
        });

        $stream->close();

        $callback = $stream->detach();

        $this->assertNull($callback);
    }

    public function testDetach()
    {
        $callback = function () {
        };
        $stream = new CallbackStream($callback);
        $ret = $stream->detach();
        $this->assertSame($callback, $ret);
    }

    public function testEof()
    {
        $stream = new CallbackStream(function () {
        });
        $ret = $stream->eof();
        $this->assertFalse($ret);

        $stream->getContents();
        $ret = $stream->eof();
        $this->assertTrue($ret);
    }

    public function testGetSize()
    {
        $stream = new CallbackStream(function () {
        });
        $ret = $stream->getSize();
        $this->assertNull($ret);
    }

    public function testTell()
    {
        $stream = new CallbackStream(function () {
        });

        $this->setExpectedException("RuntimeException");

        $stream->tell();
    }

    public function testIsSeekable()
    {
        $stream = new CallbackStream(function () {
        });
        $ret = $stream->isSeekable();
        $this->assertFalse($ret);
    }

    public function testIsWritable()
    {
        $stream = new CallbackStream(function () {
        });
        $ret = $stream->isWritable();
        $this->assertFalse($ret);
    }

    public function testIsReadable()
    {
        $stream = new CallbackStream(function () {
        });
        $ret = $stream->isReadable();
        $this->assertFalse($ret);
    }

    public function testSeek()
    {
        $stream = new CallbackStream(function () {
        });

        $this->setExpectedException("RuntimeException");

        $stream->seek(0);
    }

    public function testRewind()
    {
        $stream = new CallbackStream(function () {
        });

        $this->setExpectedException("RuntimeException");

        $stream->rewind();
    }

    public function testWrite()
    {
        $stream = new CallbackStream(function () {
        });

        $this->setExpectedException("RuntimeException");

        $stream->write('foobarbaz');
    }

    public function testRead()
    {
        $stream = new CallbackStream(function () {
        });

        $this->setExpectedException("RuntimeException");

        $stream->read(3);
    }

    public function testGetContents()
    {
        $stream = new CallbackStream(function () {
            return 'foobarbaz';
        });

        $ret = $stream->getContents();
        $this->assertSame('foobarbaz', $ret);
    }

    public function testGetMetadata()
    {
        $stream = new CallbackStream(function () {
        });

        $ret = $stream->getMetadata('stream_type');
        $this->assertSame('callback', $ret);

        $ret = $stream->getMetadata('seekable');
        $this->assertFalse($ret);

        $ret = $stream->getMetadata('eof');
        $this->assertFalse($ret);

        $all = $stream->getMetadata();
        $this->assertSame(array(
            'eof' => false,
            'stream_type' => 'callback',
            'seekable' => false,
        ), $all);

        $notExists = $stream->getMetadata('boo');
        $this->assertNull($notExists);
    }

    public function phpCallbacksForStreams()
    {
        $class = __CLASS__;

        // @codingStandardsIgnoreStart
        return array(
            'instance-method' => array(array(new self(), 'sampleCallback'),   $class . '::sampleCallback'),
            'static-method'   => array(array($class, 'sampleStaticCallback'), $class . '::sampleStaticCallback'),
        );
        // @codingStandardsIgnoreEnd
    }

    /**
     * @dataProvider phpCallbacksForStreams
     */
    public function testAllowsArbitraryPhpCallbacks($callback, $expected)
    {
        $stream = new CallbackStream($callback);
        $contents = $stream->getContents();
        $this->assertSame($expected, $contents);
    }
}
