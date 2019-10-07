<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Diactoros\Response;

use InvalidArgumentException;
use PHPUnit_Framework_TestCase as TestCase;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Response\XmlResponse;

class XmlResponseTest extends TestCase
{
    public function testConstructorAcceptsBodyAsString()
    {
        $body = 'Super valid XML';

        $response = new XmlResponse($body);
        $this->assertSame($body, (string) $response->getBody());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testConstructorAllowsPassingStatus()
    {
        $body = 'More valid XML';
        $status = 404;

        $response = new XmlResponse($body, $status);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame($body, (string) $response->getBody());
    }

    public function testConstructorAllowsPassingHeaders()
    {
        $body = '<nearly>Valid XML</nearly>';
        $status = 404;
        $headers = array(
            'x-custom' => array('foo-bar'),
        );

        $response = new XmlResponse($body, $status, $headers);
        $this->assertSame(array('foo-bar'), $response->getHeader('x-custom'));
        $this->assertSame('application/xml; charset=utf-8', $response->getHeaderLine('content-type'));
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame($body, (string) $response->getBody());
    }

    public function testAllowsStreamsForResponseBody()
    {
        $stream = $this->prophesize("Psr\Http\Message\StreamInterface");
        $body   = $stream->reveal();
        $response = new XmlResponse($body);
        $this->assertSame($body, $response->getBody());
    }

    public function invalidContent()
    {
        return array(
            'null'       => array(null),
            'true'       => array(true),
            'false'      => array(false),
            'zero'       => array(0),
            'int'        => array(1),
            'zero-float' => array(0.0),
            'float'      => array(1.1),
            'array'      => array(array('php://temp')),
            'object'     => array((object) array('php://temp')),
        );
    }

    /**
     * @dataProvider invalidContent
     */
    public function testRaisesExceptionforNonStringNonStreamBodyContent($body)
    {
        $this->setExpectedException("InvalidArgumentException");

        new XmlResponse($body);
    }

    /**
     * @group 115
     */
    public function testConstructorRewindsBodyStream()
    {
        $body = '<?xml version="1.0"?>' . \PHP_EOL . '<something>Valid XML</something>';
        $response = new XmlResponse($body);

        $actual = $response->getBody()->getContents();
        $this->assertSame($body, $actual);
    }
}
