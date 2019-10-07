<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Diactoros\Response;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\EmptyResponse;

class EmptyResponseTest extends TestCase
{
    public function testConstructor()
    {
        $response = new EmptyResponse(201);
        $this->assertInstanceOf("Zend\Diactoros\Response", $response);
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testHeaderConstructor()
    {
        $response = EmptyResponse::withHeaders(array('x-empty' => array('true')));
        $this->assertInstanceOf("Zend\Diactoros\Response", $response);
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('true', $response->getHeaderLine('x-empty'));
    }
}
