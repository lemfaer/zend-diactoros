<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Diactoros;

use InvalidArgumentException;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionProperty;
use RuntimeException;
use Zend\Diactoros\Stream;

class StreamTest extends TestCase
{
    public $tmpnam;

    /**
     * @var Stream
     */
    protected $stream;

    public function setUp()
    {
        $this->tmpnam = null;
        $this->stream = new Stream('php://memory', 'wb+');
    }

    public function tearDown()
    {
        if ($this->tmpnam && file_exists($this->tmpnam)) {
            unlink($this->tmpnam);
        }
    }

    public function testCanInstantiateWithStreamIdentifier()
    {
        $this->assertInstanceOf("Zend\Diactoros\Stream", $this->stream);
    }

    public function testCanInstantiteWithStreamResource()
    {
        $resource = fopen('php://memory', 'wb+');
        $stream   = new Stream($resource);
        $this->assertInstanceOf("Zend\Diactoros\Stream", $stream);
    }

    public function testIsReadableReturnsFalseIfStreamIsNotReadable()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        $stream = new Stream($this->tmpnam, 'w');
        $this->assertFalse($stream->isReadable());
    }

    public function testIsWritableReturnsFalseIfStreamIsNotWritable()
    {
        $stream = new Stream('php://memory', 'r');
        $this->assertFalse($stream->isWritable());
    }

    public function testToStringRetrievesFullContentsOfStream()
    {
        $message = 'foo bar';
        $this->stream->write($message);
        $this->assertSame($message, (string) $this->stream);
    }

    public function testDetachReturnsResource()
    {
        $resource = fopen('php://memory', 'wb+');
        $stream   = new Stream($resource);
        $this->assertSame($resource, $stream->detach());
    }

    public function testPassingInvalidStreamResourceToConstructorRaisesException()
    {
        $this->setExpectedException("InvalidArgumentException");

        $stream = new Stream(array('  THIS WILL NOT WORK  '));
    }

    public function testStringSerializationReturnsEmptyStringWhenStreamIsNotReadable()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $stream = new Stream($this->tmpnam, 'w');

        $this->assertSame('', $stream->__toString());
    }

    public function testCloseClosesResource()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);
        $stream->close();
        $this->assertFalse(is_resource($resource));
    }

    public function testCloseUnsetsResource()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);
        $stream->close();

        $this->assertNull($stream->detach());
    }

    public function testCloseDoesNothingAfterDetach()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);
        $detached = $stream->detach();

        $stream->close();
        $this->assertTrue(is_resource($detached));
        $this->assertSame($resource, $detached);
    }

    /**
     * @group 42
     */
    public function testSizeReportsNullWhenNoResourcePresent()
    {
        $this->stream->detach();
        $this->assertNull($this->stream->getSize());
    }

    public function testTellReportsCurrentPositionInResource()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);

        fseek($resource, 2);

        $this->assertSame(2, $stream->tell());
    }

    public function testTellRaisesExceptionIfResourceIsDetached()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);

        fseek($resource, 2);
        $stream->detach();

        $this->setExpectedException("RuntimeException", 'No resource');

        $stream->tell();
    }

    public function testEofReportsFalseWhenNotAtEndOfStream()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);

        fseek($resource, 2);
        $this->assertFalse($stream->eof());
    }

    public function testEofReportsTrueWhenAtEndOfStream()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);

        while (! feof($resource)) {
            fread($resource, 4096);
        }
        $this->assertTrue($stream->eof());
    }

    public function testEofReportsTrueWhenStreamIsDetached()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);

        fseek($resource, 2);
        $stream->detach();
        $this->assertTrue($stream->eof());
    }

    public function testIsSeekableReturnsTrueForReadableStreams()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);
        $this->assertTrue($stream->isSeekable());
    }

    public function testIsSeekableReturnsFalseForDetachedStreams()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);
        $stream->detach();
        $this->assertFalse($stream->isSeekable());
    }

    public function testSeekAdvancesToGivenOffsetOfStream()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);
        $this->assertNull($stream->seek(2));
        $this->assertSame(2, $stream->tell());
    }

    public function testRewindResetsToStartOfStream()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);
        $this->assertNull($stream->seek(2));
        $stream->rewind();
        $this->assertSame(0, $stream->tell());
    }

    public function testSeekRaisesExceptionWhenStreamIsDetached()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);
        $stream->detach();

        $this->setExpectedException("RuntimeException", 'No resource');

        $stream->seek(2);
    }

    public function testIsWritableReturnsFalseWhenStreamIsDetached()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);
        $stream->detach();
        $this->assertFalse($stream->isWritable());
    }

    public function testIsWritableReturnsTrueForWritableMemoryStream()
    {
        $stream = new Stream("php://temp", "r+b");
        $this->assertTrue($stream->isWritable());
    }

    public function provideDataForIsWritable()
    {
        return array(
            array('a',   true,  true),
            array('a+',  true,  true),
            array('a+b', true,  true),
            array('ab',  true,  true),
            array('c',   true,  true),
            array('c+',  true,  true),
            array('c+b', true,  true),
            array('cb',  true,  true),
            array('r',   true,  false),
            array('r+',  true,  true),
            array('r+b', true,  true),
            array('rb',  true,  false),
            array('rw',  true,  true),
            array('w',   true,  true),
            array('w+',  true,  true),
            array('w+b', true,  true),
            array('wb',  true,  true),
            array('x',   false, true),
            array('x+',  false, true),
            array('x+b', false, true),
            array('xb',  false, true),
        );
    }

    private function findNonExistentTempName()
    {
        while (true) {
            $tmpnam = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'diac' . uniqid();
            if (! file_exists(sys_get_temp_dir() . $tmpnam)) {
                break;
            }
        }
        return $tmpnam;
    }

    /**
     * @dataProvider provideDataForIsWritable
     */
    public function testIsWritableReturnsCorrectFlagForMode($mode, $fileShouldExist, $flag)
    {
        if ($fileShouldExist) {
            $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
            file_put_contents($this->tmpnam, 'FOO BAR');
        } else {
            // "x" modes REQUIRE that file doesn't exist, so we need to find random file name
            $this->tmpnam = $this->findNonExistentTempName();
        }
        $resource = fopen($this->tmpnam, $mode);
        $stream = new Stream($resource);
        $this->assertSame($flag, $stream->isWritable());
    }

    public function provideDataForIsReadable()
    {
        return array(
            array('a',   true,  false),
            array('a+',  true,  true),
            array('a+b', true,  true),
            array('ab',  true,  false),
            array('c',   true,  false),
            array('c+',  true,  true),
            array('c+b', true,  true),
            array('cb',  true,  false),
            array('r',   true,  true),
            array('r+',  true,  true),
            array('r+b', true,  true),
            array('rb',  true,  true),
            array('rw',  true,  true),
            array('w',   true,  false),
            array('w+',  true,  true),
            array('w+b', true,  true),
            array('wb',  true,  false),
            array('x',   false, false),
            array('x+',  false, true),
            array('x+b', false, true),
            array('xb',  false, false),
        );
    }

    /**
     * @dataProvider provideDataForIsReadable
     */
    public function testIsReadableReturnsCorrectFlagForMode($mode, $fileShouldExist, $flag)
    {
        if ($fileShouldExist) {
            $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
            file_put_contents($this->tmpnam, 'FOO BAR');
        } else {
            // "x" modes REQUIRE that file doesn't exist, so we need to find random file name
            $this->tmpnam = $this->findNonExistentTempName();
        }
        $resource = fopen($this->tmpnam, $mode);
        $stream = new Stream($resource);
        $this->assertSame($flag, $stream->isReadable());
    }

    public function testWriteRaisesExceptionWhenStreamIsDetached()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);
        $stream->detach();

        $this->setExpectedException("RuntimeException", 'No resource');

        $stream->write('bar');
    }

    public function testWriteRaisesExceptionWhenStreamIsNotWritable()
    {
        $stream = new Stream('php://memory', 'r');

        $this->setExpectedException("RuntimeException", 'Stream is not writable');

        $stream->write('bar');
    }

    public function testIsReadableReturnsFalseWhenStreamIsDetached()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'wb+');
        $stream = new Stream($resource);
        $stream->detach();

        $this->assertFalse($stream->isReadable());
    }

    public function testReadRaisesExceptionWhenStreamIsDetached()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'r');
        $stream = new Stream($resource);
        $stream->detach();

        $this->setExpectedException("RuntimeException", 'No resource');

        $stream->read(4096);
    }

    public function testReadReturnsEmptyStringWhenAtEndOfFile()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'r');
        $stream = new Stream($resource);
        while (! feof($resource)) {
            fread($resource, 4096);
        }
        $this->assertSame('', $stream->read(4096));
    }

    public function testGetContentsRisesExceptionIfStreamIsNotReadable()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'w');
        $stream = new Stream($resource);

        $this->setExpectedException("RuntimeException");

        $stream->getContents();
    }

    public function invalidResources()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        return array(
            'null' => array(null),
            'false' => array(false),
            'true' => array(true),
            'int' => array(1),
            'float' => array(1.1),
            'string-non-resource' => array('foo-bar-baz'),
            'array' => array(array(fopen($this->tmpnam, 'r+'))),
            'object' => array((object) array('resource' => fopen($this->tmpnam, 'r+'))),
        );
    }

    /**
     * @dataProvider invalidResources
     */
    public function testAttachWithNonStringNonResourceRaisesException($resource)
    {
        $this->setExpectedException("InvalidArgumentException", 'Invalid stream');

        $this->stream->attach($resource);
    }

    public function testAttachWithResourceAttachesResource()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        $resource = fopen($this->tmpnam, 'r+');
        $this->stream->attach($resource);

        $r = new ReflectionProperty($this->stream, 'resource');
        $r->setAccessible(true);
        $test = $r->getValue($this->stream);
        $this->assertSame($resource, $test);
    }

    public function testAttachWithStringRepresentingResourceCreatesAndAttachesResource()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        $this->stream->attach($this->tmpnam);

        $resource = fopen($this->tmpnam, 'r+');
        fwrite($resource, 'FooBar');

        $this->stream->rewind();
        $test = (string) $this->stream;
        $this->assertSame('FooBar', $test);
    }

    public function testGetContentsShouldGetFullStreamContents()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        $resource = fopen($this->tmpnam, 'r+');
        $this->stream->attach($resource);

        fwrite($resource, 'FooBar');

        // rewind, because current pointer is at end of stream!
        $this->stream->rewind();
        $test = $this->stream->getContents();
        $this->assertSame('FooBar', $test);
    }

    public function testGetContentsShouldReturnStreamContentsFromCurrentPointer()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        $resource = fopen($this->tmpnam, 'r+');
        $this->stream->attach($resource);

        fwrite($resource, 'FooBar');

        // seek to position 3
        $this->stream->seek(3);
        $test = $this->stream->getContents();
        $this->assertSame('Bar', $test);
    }

    public function testGetMetadataReturnsAllMetadataWhenNoKeyPresent()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        $resource = fopen($this->tmpnam, 'r+');
        $this->stream->attach($resource);

        $expected = stream_get_meta_data($resource);
        $test     = $this->stream->getMetadata();

        $this->assertSame($expected, $test);
    }

    public function testGetMetadataReturnsDataForSpecifiedKey()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        $resource = fopen($this->tmpnam, 'r+');
        $this->stream->attach($resource);

        $metadata = stream_get_meta_data($resource);
        $expected = $metadata['uri'];

        $test     = $this->stream->getMetadata('uri');

        $this->assertSame($expected, $test);
    }

    public function testGetMetadataReturnsNullIfNoDataExistsForKey()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        $resource = fopen($this->tmpnam, 'r+');
        $this->stream->attach($resource);

        $this->assertNull($this->stream->getMetadata('TOTALLY_MADE_UP'));
    }

    /**
     * @group 42
     */
    public function testGetSizeReturnsStreamSize()
    {
        $resource = fopen(__FILE__, 'r');
        $expected = fstat($resource);
        $stream = new Stream($resource);
        $this->assertSame($expected['size'], $stream->getSize());
    }

    /**
     * @group 67
     */
    public function testRaisesExceptionOnConstructionForNonStreamResources()
    {
        $resource = $this->getResourceFor67();
        if (false === $resource) {
            $this->markTestSkipped('No acceptable resource available to test ' . __METHOD__);
        }

        $this->setExpectedException("InvalidArgumentException", 'stream');

        new Stream($resource);
    }

    /**
     * @group 67
     */
    public function testRaisesExceptionOnAttachForNonStreamResources()
    {
        $resource = $this->getResourceFor67();
        if (false === $resource) {
            $this->markTestSkipped('No acceptable resource available to test ' . __METHOD__);
        }

        $stream = new Stream(__FILE__);

        $this->setExpectedException("InvalidArgumentException", 'stream');

        $stream->attach($resource);
    }

    public function getResourceFor67()
    {
        if (function_exists('curl_init')) {
            return curl_init();
        }

        if (function_exists('shmop_open')) {
            return shmop_open(ftok(__FILE__, 't'), 'c', 0644, 100);
        }

        if (function_exists('gmp_init')) {
            return gmp_init(1);
        }

        if (function_exists('imagecreate')) {
            return imagecreate(200, 200);
        }

        return false;
    }

    public function testCanReadContentFromNotSeekableResource()
    {
        $this->tmpnam = tempnam(sys_get_temp_dir(), 'diac');
        file_put_contents($this->tmpnam, 'FOO BAR');
        $resource = fopen($this->tmpnam, 'r');
        $stream = $this
            ->getMockBuilder("Zend\Diactoros\Stream")
            ->setConstructorArgs(array($resource))
            ->setMethods(array('isSeekable'))
            ->getMock();

        $stream->expects($this->any())->method('isSeekable')
            ->will($this->returnValue(false));

        $this->assertSame('FOO BAR', $stream->__toString());
    }

    /**
     * @group 42
     */
    public function testSizeReportsNullForPhpInputStreams()
    {
        $resource = fopen('php://input', 'r');
        $stream = new Stream($resource);
        $this->assertNull($stream->getSize());
    }
}
