<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Diactoros;

use PHPUnit_Framework_TestCase as TestCase;
use ReflectionMethod;
use ReflectionProperty;
use UnexpectedValueException;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\UploadedFile;
use Zend\Diactoros\Uri;

class ServerRequestFactoryTest extends TestCase
{
    public function testReturnsServerValueUnchangedIfHttpAuthorizationHeaderIsPresent()
    {
        $server = array(
            'HTTP_AUTHORIZATION' => 'token',
            'HTTP_X_Foo' => 'bar',
        );
        $this->assertSame($server, \Zend\Diactoros\normalizeServer($server));
    }

    public function testMarshalsExpectedHeadersFromServerArray()
    {
        $server = array(
            'HTTP_COOKIE' => 'COOKIE',
            'HTTP_AUTHORIZATION' => 'token',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_FOO_BAR' => 'FOOBAR',
            'CONTENT_MD5' => 'CONTENT-MD5',
            'CONTENT_LENGTH' => 'UNSPECIFIED',
        );

        $expected = array(
            'cookie' => 'COOKIE',
            'authorization' => 'token',
            'content-type' => 'application/json',
            'accept' => 'application/json',
            'x-foo-bar' => 'FOOBAR',
            'content-md5' => 'CONTENT-MD5',
            'content-length' => 'UNSPECIFIED',
        );

        $this->assertSame($expected, \Zend\Diactoros\marshalHeadersFromSapi($server));
    }

    public function testMarshalInvalidHeadersStrippedFromServerArray()
    {
        $server = array(
            'COOKIE' => 'COOKIE',
            'HTTP_AUTHORIZATION' => 'token',
            'MD5' => 'CONTENT-MD5',
            'CONTENT_LENGTH' => 'UNSPECIFIED',
        );

        //Headers that don't begin with HTTP_ or CONTENT_ will not be returned
        $expected = array(
            'authorization' => 'token',
            'content-length' => 'UNSPECIFIED',
        );
        $this->assertSame($expected, \Zend\Diactoros\marshalHeadersFromSapi($server));
    }

    public function testMarshalsVariablesPrefixedByApacheFromServerArray()
    {
        // Non-prefixed versions will be preferred
        $server = array(
            'HTTP_X_FOO_BAR' => 'nonprefixed',
            'REDIRECT_HTTP_AUTHORIZATION' => 'token',
            'REDIRECT_HTTP_X_FOO_BAR' => 'prefixed',
        );

        $expected = array(
            'authorization' => 'token',
            'x-foo-bar' => 'nonprefixed',
        );

        $this->assertEquals($expected, \Zend\Diactoros\marshalHeadersFromSapi($server));
    }

    public function testMarshalRequestUriUsesIISUnencodedUrlValueIfPresentAndUrlWasRewritten()
    {
        $server = array(
            'IIS_WasUrlRewritten' => '1',
            'UNENCODED_URL' => '/foo/bar',
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, array());

        $this->assertSame($server['UNENCODED_URL'], $uri->getPath());
    }

    public function testMarshalRequestUriStripsSchemeHostAndPortInformationWhenPresent()
    {
        $server = array(
            'REQUEST_URI' => 'http://example.com:8000/foo/bar',
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, array());

        $this->assertSame('/foo/bar', $uri->getPath());
    }

    public function testMarshalRequestUriUsesOrigPathInfoIfPresent()
    {
        $server = array(
            'ORIG_PATH_INFO' => '/foo/bar',
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, array());

        $this->assertSame('/foo/bar', $uri->getPath());
    }

    public function testMarshalRequestUriFallsBackToRoot()
    {
        $server = array();

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, array());

        $this->assertSame('/', $uri->getPath());
    }

    public function testMarshalHostAndPortUsesHostHeaderWhenPresent()
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri('http://example.com/'));
        $request = $request->withMethod('GET');
        $request = $request->withHeader('Host', 'example.com');

        $uri = \Zend\Diactoros\marshalUriFromSapi(array(), $request->getHeaders());

        $this->assertSame('example.com', $uri->getHost());
        $this->assertNull($uri->getPort());
    }

    public function testMarshalHostAndPortWillDetectPortInHostHeaderWhenPresent()
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri('http://example.com:8000/'));
        $request = $request->withMethod('GET');
        $request = $request->withHeader('Host', 'example.com:8000');

        $uri = \Zend\Diactoros\marshalUriFromSapi(array(), $request->getHeaders());

        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame(8000, $uri->getPort());
    }

    public function testMarshalHostAndPortReturnsEmptyValuesIfNoHostHeaderAndNoServerName()
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri());

        $uri = \Zend\Diactoros\marshalUriFromSapi(array(), $request->getHeaders());

        $this->assertSame('', $uri->getHost());
        $this->assertNull($uri->getPort());
    }

    public function testMarshalHostAndPortReturnsServerNameForHostWhenPresent()
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri('http://example.com/'));

        $server = array(
            'SERVER_NAME' => 'example.com',
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $request->getHeaders());

        $this->assertSame('example.com', $uri->getHost());
        $this->assertNull($uri->getPort());
    }

    public function testMarshalHostAndPortReturnsServerPortForPortWhenPresentWithServerName()
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri());

        $server  = array(
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => 8000,
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $request->getHeaders());

        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame(8000, $uri->getPort());
    }

    public function testMarshalHostAndPortReturnsServerNameForHostIfServerAddrPresentButHostIsNotIpv6Address()
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri('http://example.com/'));

        $server  = array(
            'SERVER_ADDR' => '127.0.0.1',
            'SERVER_NAME' => 'example.com',
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $request->getHeaders());

        $this->assertSame('example.com', $uri->getHost());
    }

    public function testMarshalHostAndPortReturnsServerAddrForHostIfPresentAndHostIsIpv6Address()
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri());

        $server  = array(
            'SERVER_ADDR' => 'FE80::0202:B3FF:FE1E:8329',
            'SERVER_NAME' => '[FE80::0202:B3FF:FE1E:8329]',
            'SERVER_PORT' => 8000,
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $request->getHeaders());

        $this->assertSame(strtolower('[FE80::0202:B3FF:FE1E:8329]'), $uri->getHost());
        $this->assertSame(8000, $uri->getPort());
    }

    public function testMarshalHostAndPortWillDetectPortInIpv6StyleHost()
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri());

        $server  = array(
            'SERVER_ADDR' => 'FE80::0202:B3FF:FE1E:8329',
            'SERVER_NAME' => '[FE80::0202:B3FF:FE1E:8329:80]',
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $request->getHeaders());

        $this->assertSame(strtolower('[FE80::0202:B3FF:FE1E:8329]'), $uri->getHost());
        $this->assertNull($uri->getPort());
    }

    /**
     * @return array
     */
    public function httpsParamProvider()
    {
        return array(
            'lowercase' => array('https'),
            'uppercase' => array('HTTPS'),
        );
    }

    /**
     * @dataProvider httpsParamProvider
     * @param string $param
     */
    public function testMarshalUriDetectsHttpsSchemeFromServerValue($param)
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri('http://example.com/'));
        $request = $request->withHeader('Host', 'example.com');

        $server  = array(
            $param => true,
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $request->getHeaders());

        $this->assertInstanceOf("Zend\Diactoros\Uri", $uri);
        $this->assertSame('https', $uri->getScheme());
    }

    /**
     * @return iterable
     */
    public function httpsDisableParamProvider()
    {
        $all = array();
        foreach ($this->httpsParamProvider() as $key => $data) {
            $param = array_shift($data);
            foreach (array('lowercase-off', 'uppercase-off') as $type) {
                $key = sprintf('%s-%s', $key, $type);
                $value = false !== strpos($type, 'lowercase') ? 'off' : 'OFF';
                $all[$key] = array($param, $value);
            }
        }

        return $all;
    }

    /**
     * @dataProvider httpsDisableParamProvider
     * @param string $param
     * @param string $value
     */
    public function testMarshalUriUsesHttpSchemeIfHttpsServerValueEqualsOff($param, $value)
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri('http://example.com/'));
        $request = $request->withHeader('Host', 'example.com');

        $server  = array(
            $param => $value,
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $request->getHeaders());

        $this->assertInstanceOf("Zend\Diactoros\Uri", $uri);
        $this->assertSame('http', $uri->getScheme());
    }

    /**
     * @dataProvider httpsParamProvider
     * @param string $xForwardedProto
     */
    public function testMarshalUriDetectsHttpsSchemeFromXForwardedProtoValue($xForwardedProto)
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri('http://example.com/'));
        $request = $request->withHeader('Host', 'example.com');
        $request = $request->withHeader('X-Forwarded-Proto', $xForwardedProto);

        $server  = array();

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $request->getHeaders());

        $this->assertInstanceOf("Zend\Diactoros\Uri", $uri);
        $this->assertSame('https', $uri->getScheme());
    }

    public function testMarshalUriStripsQueryStringFromRequestUri()
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri('http://example.com/'));
        $request = $request->withHeader('Host', 'example.com');

        $server = array(
            'REQUEST_URI' => '/foo/bar?foo=bar',
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $request->getHeaders());

        $this->assertInstanceOf("Zend\Diactoros\Uri", $uri);
        $this->assertSame('/foo/bar', $uri->getPath());
    }

    public function testMarshalUriInjectsQueryStringFromServer()
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri('http://example.com/'));
        $request = $request->withHeader('Host', 'example.com');

        $server = array(
            'REQUEST_URI' => '/foo/bar?foo=bar',
            'QUERY_STRING' => 'bar=baz',
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $request->getHeaders());

        $this->assertInstanceOf("Zend\Diactoros\Uri", $uri);
        $this->assertSame('bar=baz', $uri->getQuery());
    }

    public function testMarshalUriInjectsFragmentFromServer()
    {
        $request = new ServerRequest();
        $request = $request->withUri(new Uri('http://example.com/'));
        $request = $request->withHeader('Host', 'example.com');

        $server = array(
            'REQUEST_URI' => '/foo/bar#foo',
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $request->getHeaders());

        $this->assertInstanceOf("Zend\Diactoros\Uri", $uri);
        $this->assertSame('foo', $uri->getFragment());
    }

    public function testCanCreateServerRequestViaFromGlobalsMethod()
    {
        $server = array(
            'SERVER_PROTOCOL' => '1.1',
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'application/json',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'bar=baz',
        );

        $cookies = $query = $body = $files = array(
            'bar' => 'baz',
        );

        $cookies['cookies'] = true;
        $query['query']     = true;
        $body['body']       = true;
        $files = array(
            'files' => array(
                'tmp_name' => 'php://temp',
                'size'     => 0,
                'error'    => 0,
                'name'     => 'foo.bar',
                'type'     => 'text/plain',
            )
        );
        $expectedFiles = array(
            'files' => new UploadedFile('php://temp', 0, 0, 'foo.bar', 'text/plain')
        );

        $request = ServerRequestFactory::fromGlobals($server, $query, $body, $cookies, $files);
        $this->assertInstanceOf("Zend\Diactoros\ServerRequest", $request);
        $this->assertSame($cookies, $request->getCookieParams());
        $this->assertSame($query, $request->getQueryParams());
        $this->assertSame($body, $request->getParsedBody());
        $this->assertEquals($expectedFiles, $request->getUploadedFiles());
        $this->assertEmpty($request->getAttributes());
        $this->assertSame('1.1', $request->getProtocolVersion());
    }

    public function testFromGlobalsUsesCookieHeaderInsteadOfCookieSuperGlobal()
    {
        $_COOKIE = array(
            'foo_bar' => 'bat',
        );
        $_SERVER['HTTP_COOKIE'] = 'foo_bar=baz';

        $request = ServerRequestFactory::fromGlobals();
        $this->assertSame(array('foo_bar' => 'baz'), $request->getCookieParams());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState
     */
    public function testCreateFromGlobalsShouldPreserveKeysWhenCreatedWithAZeroValue()
    {
        $_SERVER['HTTP_ACCEPT'] = '0';
        $_SERVER['CONTENT_LENGTH'] = '0';

        $request = ServerRequestFactory::fromGlobals();
        $this->assertSame('0', $request->getHeaderLine('accept'));
        $this->assertSame('0', $request->getHeaderLine('content-length'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState
     */
    public function testCreateFromGlobalsShouldNotPreserveKeysWhenCreatedWithAnEmptyValue()
    {
        $_SERVER['HTTP_ACCEPT'] = '';
        $_SERVER['CONTENT_LENGTH'] = '';

        $request = ServerRequestFactory::fromGlobals();

        $this->assertFalse($request->hasHeader('accept'));
        $this->assertFalse($request->hasHeader('content-length'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFromGlobalsUsesCookieSuperGlobalWhenCookieHeaderIsNotSet()
    {
        $_COOKIE = array(
            'foo_bar' => 'bat',
        );

        $request = ServerRequestFactory::fromGlobals();
        $this->assertSame(array('foo_bar' => 'bat'), $request->getCookieParams());
    }

    public function cookieHeaderValues()
    {
        return array(
            'ows-without-fold' => array(
                "\tfoo=bar ",
                array('foo' => 'bar'),
            ),
            'url-encoded-value' => array(
                'foo=bar%3B+',
                array('foo' => 'bar; '),
            ),
            'double-quoted-value' => array(
                'foo="bar"',
                array('foo' => 'bar'),
            ),
            'multiple-pairs' => array(
                'foo=bar; baz="bat"; bau=bai',
                array('foo' => 'bar', 'baz' => 'bat', 'bau' => 'bai'),
            ),
            'same-name-pairs' => array(
                'foo=bar; foo="bat"',
                array('foo' => 'bat'),
            ),
            'period-in-name' => array(
                'foo.bar=baz',
                array('foo.bar' => 'baz'),
            ),
        );
    }

    /**
     * @dataProvider cookieHeaderValues
     * @param string $cookieHeader
     * @param array $expectedCookies
     */
    public function testCookieHeaderVariations($cookieHeader, array $expectedCookies)
    {
        $_SERVER['HTTP_COOKIE'] = $cookieHeader;

        $request = ServerRequestFactory::fromGlobals();
        $this->assertSame($expectedCookies, $request->getCookieParams());
    }

    public function testNormalizeServerUsesMixedCaseAuthorizationHeaderFromApacheWhenPresent()
    {
        $server = \Zend\Diactoros\normalizeServer(array(), function () {
            return array('Authorization' => 'foobar');
        });

        $this->assertArrayHasKey('HTTP_AUTHORIZATION', $server);
        $this->assertSame('foobar', $server['HTTP_AUTHORIZATION']);
    }

    public function testNormalizeServerUsesLowerCaseAuthorizationHeaderFromApacheWhenPresent()
    {
        $server = \Zend\Diactoros\normalizeServer(array(), function () {
            return array('authorization' => 'foobar');
        });

        $this->assertArrayHasKey('HTTP_AUTHORIZATION', $server);
        $this->assertSame('foobar', $server['HTTP_AUTHORIZATION']);
    }

    public function testNormalizeServerReturnsArrayUnalteredIfApacheHeadersDoNotContainAuthorization()
    {
        $expected = array('FOO_BAR' => 'BAZ');

        $server = \Zend\Diactoros\normalizeServer($expected, function () {
            return array();
        });

        $this->assertSame($expected, $server);
    }

    /**
     * @group 57
     * @group 56
     */
    public function testNormalizeFilesReturnsOnlyActualFilesWhenOriginalFilesContainsNestedAssociativeArrays()
    {
        $files = array('fooFiles' => array(
            'tmp_name' => array('file' => 'php://temp'),
            'size'     => array('file' => 0),
            'error'    => array('file' => 0),
            'name'     => array('file' => 'foo.bar'),
            'type'     => array('file' => 'text/plain'),
        ));

        $normalizedFiles = \Zend\Diactoros\normalizeUploadedFiles($files);

        $this->assertCount(1, $normalizedFiles['fooFiles']);
    }

    public function testMarshalProtocolVersionRisesExceptionIfVersionIsNotRecognized()
    {
        $this->setExpectedException("UnexpectedValueException");
        \Zend\Diactoros\marshalProtocolVersionFromSapi(array('SERVER_PROTOCOL' => 'dadsa/1.0'));
    }

    public function testMarshalProtocolReturnsDefaultValueIfHeaderIsNotPresent()
    {
        $version = \Zend\Diactoros\marshalProtocolVersionFromSapi(array());
        $this->assertSame('1.1', $version);
    }

    /**
     * @dataProvider marshalProtocolVersionProvider
     */
    public function testMarshalProtocolVersionReturnsHttpVersions($protocol, $expected)
    {
        $version = \Zend\Diactoros\marshalProtocolVersionFromSapi(array('SERVER_PROTOCOL' => $protocol));
        $this->assertSame($expected, $version);
    }

    public function marshalProtocolVersionProvider()
    {
        return array(
            'HTTP/1.0' => array('HTTP/1.0', '1.0'),
            'HTTP/1.1' => array('HTTP/1.1', '1.1'),
            'HTTP/2'   => array('HTTP/2', '2'),
        );
    }

    public function testMarshalRequestUriPrefersRequestUriServerParamWhenXOriginalUrlButNoXRewriteUrlPresent()
    {
        $headers = array(
            'X-Original-URL' => '/hijack-attempt',
        );
        $server = array(
            'REQUEST_URI' => 'https://example.com/requested/path',
        );

        $uri = \Zend\Diactoros\marshalUriFromSapi($server, $headers);
        $this->assertSame('/requested/path', $uri->getPath());
    }

    public function testServerRequestFactoryHasAWritableEmptyBody()
    {
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', '/');
        $body = $request->getBody();

        $this->assertTrue($body->isWritable());
        $this->assertTrue($body->isSeekable());
        $this->assertSame(0, $body->getSize());
    }
}
