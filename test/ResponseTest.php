<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Diactoros;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class ResponseTest extends TestCase
{
    /**
     * @var Response
    */
    protected $response;

    public function setUp()
    {
        $this->response = new Response();
    }

    public function testStatusCodeIs200ByDefault()
    {
        $this->assertSame(200, $this->response->getStatusCode());
    }

    public function testStatusCodeMutatorReturnsCloneWithChanges()
    {
        $response = $this->response->withStatus(400);
        $this->assertNotSame($this->response, $response);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testReasonPhraseDefaultsToStandards()
    {
        $response = $this->response->withStatus(422);
        $this->assertSame('Unprocessable Entity', $response->getReasonPhrase());
    }

    public function ianaCodesReasonPhrasesProvider()
    {
        $ianaHttpStatusCodes = new DOMDocument();

        libxml_set_streams_context(
            stream_context_create(
                array(
                    'http' => array(
                        'method'  => 'GET',
                        'timeout' => 30,
                        'user_agent' => 'PHP',
                    ),
                )
            )
        );

        $ianaHttpStatusCodes->load('https://www.iana.org/assignments/http-status-codes/http-status-codes.xml');

        if (! $ianaHttpStatusCodes->relaxNGValidate(__DIR__ . '/TestAsset/http-status-codes.rng')) {
            self::fail('Unable to retrieve IANA response status codes due to timeout or invalid XML');
        }

        $ianaCodesReasonPhrases = array();

        $xpath = new DOMXPath($ianaHttpStatusCodes);
        $xpath->registerNamespace('ns', 'http://www.iana.org/assignments');

        $records = $xpath->query('//ns:record');

        foreach ($records as $record) {
            $value = $xpath->query('.//ns:value', $record)->item(0)->nodeValue;
            $description = $xpath->query('.//ns:description', $record)->item(0)->nodeValue;

            if (in_array($description, array('Unassigned', '(Unused)'))) {
                continue;
            }

            if (preg_match('/^([0-9]+)\s*\-\s*([0-9]+)$/', $value, $matches)) {
                for ($value = $matches[1]; $value <= $matches[2]; $value++) {
                    $ianaCodesReasonPhrases[] = array($value, $description);
                }
            } else {
                $ianaCodesReasonPhrases[] = array($value, $description);
            }
        }

        return $ianaCodesReasonPhrases;
    }

    /**
     * @dataProvider ianaCodesReasonPhrasesProvider
     */
    public function testReasonPhraseDefaultsAgainstIana($code, $reasonPhrase)
    {
        $response = $this->response->withStatus($code);
        $this->assertSame($reasonPhrase, $response->getReasonPhrase());
    }

    public function testCanSetCustomReasonPhrase()
    {
        $response = $this->response->withStatus(422, 'Foo Bar!');
        $this->assertSame('Foo Bar!', $response->getReasonPhrase());
    }

    public function invalidReasonPhrases()
    {
        return array(
            'true' => array(true),
            'false' => array(false),
            'array' => array(array(200)),
            'object' => array((object) array('reasonPhrase' => 'Ok')),
            'integer' => array(98),
            'float' => array(400.5),
            'null' => array(null),
        );
    }

    /**
     * @dataProvider invalidReasonPhrases
     */
    public function testWithStatusRaisesAnExceptionForNonStringReasonPhrases($invalidReasonPhrase)
    {
        $this->setExpectedException("InvalidArgumentException");

        $this->response->withStatus(422, $invalidReasonPhrase);
    }

    public function testConstructorRaisesExceptionForInvalidStream()
    {
        $this->setExpectedException("InvalidArgumentException");

        new Response(array('TOTALLY INVALID'));
    }

    public function testConstructorCanAcceptAllMessageParts()
    {
        $body = new Stream('php://memory');
        $status = 302;
        $headers = array(
            'location' => array('http://example.com/'),
        );

        $response = new Response($body, $status, $headers);
        $this->assertSame($body, $response->getBody());
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($headers, $response->getHeaders());
    }

    /**
     * @dataProvider validStatusCodes
     */
    public function testCreateWithValidStatusCodes($code)
    {
        $response = $this->response->withStatus($code);

        $result = $response->getStatusCode();

        $this->assertSame((int) $code, $result);
        $this->assertInternalType('int', $result);
    }

    public function validStatusCodes()
    {
        return array(
            'minimum' => array(100),
            'middle' => array(300),
            'string-integer' => array('300'),
            'maximum' => array(599),
        );
    }

    /**
     * @dataProvider invalidStatusCodes
     */
    public function testCannotSetInvalidStatusCode($code)
    {
        $this->setExpectedException("InvalidArgumentException");

        $this->response->withStatus($code);
    }

    public function invalidStatusCodes()
    {
        return array(
            'true' => array(true),
            'false' => array(false),
            'array' => array(array(200)),
            'object' => array((object) array('statusCode' => 200)),
            'too-low' => array(99),
            'float' => array(400.5),
            'too-high' => array(600),
            'null' => array(null),
            'string' => array('foo'),
        );
    }

    public function invalidResponseBody()
    {
        return array(
            'true'       => array(true),
            'false'      => array(false),
            'int'        => array(1),
            'float'      => array(1.1),
            'array'      => array(array('BODY')),
            'stdClass'   => array((object) array('body' => 'BODY')),
        );
    }

    /**
     * @dataProvider invalidResponseBody
     */
    public function testConstructorRaisesExceptionForInvalidBody($body)
    {
        $this->setExpectedException("InvalidArgumentException", 'stream');

        new Response($body);
    }


    public function invalidHeaderTypes()
    {
        return array(
            'indexed-array' => array(array(array('INVALID')), 'header name'),
            'null' => array(array('x-invalid-null' => null)),
            'true' => array(array('x-invalid-true' => true)),
            'false' => array(array('x-invalid-false' => false)),
            'object' => array(array('x-invalid-object' => (object) array('INVALID'))),
        );
    }

    /**
     * @dataProvider invalidHeaderTypes
     * @group 99
     */
    public function testConstructorRaisesExceptionForInvalidHeaders($headers, $contains = 'header value type')
    {
        $this->setExpectedException("InvalidArgumentException", $contains);

        new Response('php://memory', 200, $headers);
    }

    public function testReasonPhraseCanBeEmpty()
    {
        $response = $this->response->withStatus(555);
        $this->assertInternalType('string', $response->getReasonPhrase());
        $this->assertEmpty($response->getReasonPhrase());
    }

    public function headersWithInjectionVectors()
    {
        return array(
            'name-with-cr'           => array("X-Foo\r-Bar", 'value'),
            'name-with-lf'           => array("X-Foo\n-Bar", 'value'),
            'name-with-crlf'         => array("X-Foo\r\n-Bar", 'value'),
            'name-with-2crlf'        => array("X-Foo\r\n\r\n-Bar", 'value'),
            'value-with-cr'          => array('X-Foo-Bar', "value\rinjection"),
            'value-with-lf'          => array('X-Foo-Bar', "value\ninjection"),
            'value-with-crlf'        => array('X-Foo-Bar', "value\r\ninjection"),
            'value-with-2crlf'       => array('X-Foo-Bar', "value\r\n\r\ninjection"),
            'array-value-with-cr'    => array('X-Foo-Bar', array("value\rinjection")),
            'array-value-with-lf'    => array('X-Foo-Bar', array("value\ninjection")),
            'array-value-with-crlf'  => array('X-Foo-Bar', array("value\r\ninjection")),
            'array-value-with-2crlf' => array('X-Foo-Bar', array("value\r\n\r\ninjection")),
        );
    }

    /**
     * @group ZF2015-04
     * @dataProvider headersWithInjectionVectors
     */
    public function testConstructorRaisesExceptionForHeadersWithCRLFVectors($name, $value)
    {
        $this->setExpectedException("InvalidArgumentException");

        new Response('php://memory', 200, array($name => $value));
    }
}
