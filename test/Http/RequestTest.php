<?php

namespace PHPFastCGI\Test\FastCGIDaemon\Http;

use PHPFastCGI\FastCGIDaemon\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Test that the request builder is correctly building the PSR-7 request
 * message.
 */
class RequestTest extends TestCase
{
    /**
     * Test that the request builder is correctly building the request messages.
     */
    public function testRequest()
    {
        $expectedQuery   = ['bar' => 'foo', 'world' => 'hello'];
        $expectedPost    = ['foo' => 'bar', 'hello' => 'world'];
        $expectedCookies = ['one' => 'two', 'three' => 'four', 'five' => 'six'];

        // Set up FastCGI params and content
        $params = [
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD'  => 'POST',
            'content_type'    => 'application/x-www-form-urlencoded',
            'REQUEST_URI'     => '/my-page',
            'QUERY_STRING'    => 'bar=foo&world=hello',
            'HTTP_cookie'     => 'one=two; three=four; five=six',
        ];

        // Set up the FastCGI stdin data stream resource
        $content = 'foo=bar&hello=world';

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);

        // Create the request
        $request = new Request($params, $stream);

        // Check request object
        $this->assertEquals($expectedQuery,   $request->getQuery());
        $this->assertEquals($expectedPost,    $request->getPost());
        $this->assertEquals($expectedCookies, $request->getCookies());
        $this->assertEquals($stream,          $request->getStdin());

        // Check the PSR server request
        rewind($stream);
        $serverRequest = $request->getServerRequest();
        $this->assertEquals($params['REQUEST_URI'], $serverRequest->getUri()->getPath());
        $this->assertEquals($expectedQuery,         $serverRequest->getQueryParams());
        $this->assertEquals($expectedPost,          $serverRequest->getParsedBody());
        $this->assertEquals($expectedCookies,       $serverRequest->getCookieParams());
        $this->assertEquals($content,      (string) $serverRequest->getBody());

        // Check the HttpFoundation request
        rewind($stream);
        $httpFoundationRequest = $request->getHttpFoundationRequest();
        $this->assertEquals($params['REQUEST_URI'], $httpFoundationRequest->getRequestUri());
        $this->assertEquals($expectedQuery,         $httpFoundationRequest->query->all());
        $this->assertEquals($expectedPost,          $httpFoundationRequest->request->all());
        $this->assertEquals($expectedCookies,       $httpFoundationRequest->cookies->all());
        $this->assertEquals($content,               $httpFoundationRequest->getContent());
    }

    public function testMultipartContent()
    {
        $expectedPost    = ['foo' => 'A normal stream', 'baz' => 'string'];

        // Set up FastCGI params and content
        $params = [
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD'  => 'POST',
            'content_type'    => 'multipart/form-data; boundary="578de3b0e3c46.2334ba3"',
            'REQUEST_URI'     => '/my-page',
        ];

        // Set up the FastCGI stdin data stream resource
        $content = <<<HTTP
--578de3b0e3c46.2334ba3
Content-Disposition: form-data; name="foo"
Content-Length: 15

A normal stream
--578de3b0e3c46.2334ba3
Content-Disposition: form-data; name="bar"; filename="bar.png"
Content-Length: 71
Content-Type: image/png

?PNG

???
IHDR??? ??? ?????? ???? IDATxc???51?)?:??????IEND?B`?
--578de3b0e3c46.2334ba3
Content-Type: text/plain
Content-Disposition: form-data; name="baz"
Content-Length: 6

string
--578de3b0e3c46.2334ba3--
HTTP;

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);

        // Create the request
        $request = new Request($params, $stream);

        // Check request object
        $this->assertEquals($expectedPost,    $request->getPost());
        $this->assertEquals($stream,          $request->getStdin());

        // Check the PSR server request
        rewind($stream);
        $serverRequest = $request->getServerRequest();
        $this->assertEquals($expectedPost, $serverRequest->getParsedBody());
        $this->assertCount(1,              $serverRequest->getUploadedFiles());
        $this->assertEquals($content,      $serverRequest->getBody()->__toString());

        // Check the HttpFoundation request
        rewind($stream);
        $httpFoundationRequest = $request->getHttpFoundationRequest();
        $this->assertEquals($expectedPost, $httpFoundationRequest->request->all());
        $this->assertCount(1,              $httpFoundationRequest->files->all());
        $this->assertEquals($content,      $httpFoundationRequest->getContent());
    }

    public function testMultipartContentWithMultipleFiles()
    {
        $expectedPost    = ['foo' => 'A normal stream', 'baz' => 'string'];

        // Set up FastCGI params and content
        $params = [
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD'  => 'POST',
            'content_type'    => 'multipart/form-data; boundary="578de3b0e3c46.2334ba3"',
            'REQUEST_URI'     => '/my-page',
        ];

        // Set up the FastCGI stdin data stream resource
        $content = <<<HTTP
--578de3b0e3c46.2334ba3
Content-Disposition: form-data; name="foo"
Content-Length: 15

A normal stream
--578de3b0e3c46.2334ba3
Content-Disposition: form-data; name="one[]"; filename="foo.png"
Content-Length: 71
Content-Type: image/png

?PNG

???
IHDR??? ??? ?????? ???? IDATxc???51?)?:??????IEND?B`?
--578de3b0e3c46.2334ba3
--578de3b0e3c46.2334ba3
Content-Disposition: form-data; name="one[]"; filename="bar.png"
Content-Length: 71
Content-Type: image/png

?PNG

???
IHDR??? ??? ?????? ???? IDATxc???51?)?:??????IEND?B`?
--578de3b0e3c46.2334ba3
Content-Disposition: form-data; name="two[item-a]"; filename="bar.png"
Content-Length: 71
Content-Type: image/png

?PNG

???
IHDR??? ??? ?????? ???? IDATxc???51?)?:??????IEND?B`?
--578de3b0e3c46.2334ba3
Content-Disposition: form-data; name="three[item][]"; filename="foo.png"
Content-Length: 71
Content-Type: image/png

?PNG

???
IHDR??? ??? ?????? ???? IDATxc???51?)?:??????IEND?B`?
--578de3b0e3c46.2334ba3
Content-Disposition: form-data; name="three[item][]"; filename="bar.png"
Content-Length: 71
Content-Type: image/png

?PNG

???
IHDR??? ??? ?????? ???? IDATxc???51?)?:??????IEND?B`?
--578de3b0e3c46.2334ba3
Content-Disposition: form-data; name="four[item_a][item_b[]"; filename="foo.png"
Content-Length: 71
Content-Type: image/png

?PNG

???
IHDR??? ??? ?????? ???? IDATxc???51?)?:??????IEND?B`?
Content-Disposition: form-data; name="four[item_a][item_b[]"; filename="bar.png"
Content-Length: 71
Content-Type: image/png

?PNG

???
IHDR??? ??? ?????? ???? IDATxc???51?)?:??????IEND?B`?
--578de3b0e3c46.2334ba3
Content-Type: text/plain
Content-Disposition: form-data; name="baz"
Content-Length: 6

string
--578de3b0e3c46.2334ba3--
HTTP;

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);

        // Create the request
        $request = new Request($params, $stream);

        // Check request object
        $this->assertEquals($expectedPost,    $request->getPost());
        $this->assertEquals($stream,          $request->getStdin());

        // Check the PSR server request
        rewind($stream);
        $serverRequest = $request->getServerRequest();
        $this->assertEquals($expectedPost, $serverRequest->getParsedBody());
        $files = $serverRequest->getUploadedFiles();
        $this->assertNotEmpty($files['one']);
        $this->assertCount(2, $files['one']);

        $this->assertNotEmpty($files['two']);
        $this->assertCount(1, $files['two']);
        $this->assertNotEmpty($files['two']['item-a']);

        $this->assertNotEmpty($files['three']);
        $this->assertCount(1, $files['three']);
        $this->assertCount(2, $files['three']['item']);

        $this->assertNotEmpty($files['four']);
        $this->assertNotEmpty($files['four']['item_a']);
        $this->assertNotEmpty($files['four']['item_a']['item_b']);
        $this->assertCount(2, $files['three']['item_a']['item_b']);

        // Check the HttpFoundation request
        rewind($stream);
        $httpFoundationRequest = $request->getHttpFoundationRequest();
        $this->assertEquals($expectedPost, $httpFoundationRequest->request->all());
        $this->assertCount(7,              $httpFoundationRequest->files->all());

    }
}
