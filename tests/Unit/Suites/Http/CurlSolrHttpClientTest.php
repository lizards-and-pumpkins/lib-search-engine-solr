<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http;

use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\Exception\SolrConnectionException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\CurlSolrHttpClient
 */
class CurlSolrHttpClientTest extends TestCase
{
    /**
     * @var CurlSolrHttpClient
     */
    private $client;

    /**
     * @var string
     */
    private static $requestUrl;

    /**
     * @var mixed[]
     */
    private static $curlOptionsSet = [];

    /**
     * @var string
     */
    private static $returnType = 'json';

    public static function trackRequestUrl(string $url)
    {
        self::$requestUrl = $url;
    }

    /**
     * @param int $option
     * @param mixed $value
     */
    public static function trackCurlOptionSet(int $option, $value)
    {
        self::$curlOptionsSet[$option] = $value;
    }

    public static function getReturnType() : string
    {
        return self::$returnType;
    }

    protected function setUp()
    {
        $testSolrConnectionPath = 'http://localhost:8983/solr/core/';
        $this->client = new CurlSolrHttpClient($testSolrConnectionPath);
    }

    protected function tearDown()
    {
        self::$requestUrl = null;
        self::$curlOptionsSet = [];
        self::$returnType = 'json';
    }

    public function testInstanceOfSolrHttpClientIsReturned()
    {
        $this->assertInstanceOf(SolrHttpClient::class, $this->client);
    }

    public function testUpdateRequestHasUpdateServlet()
    {
        $parameters = [];
        $this->client->update($parameters);

        $path = parse_url(self::$requestUrl, PHP_URL_PATH);
        $lastToken = preg_replace('/.*\//', '', $path);

        $this->assertSame(SolrHttpClient::UPDATE_SERVLET, $lastToken);
    }

    public function testUpdateRequestHasCommitParameterSetToTrue()
    {
        $parameters = [];
        $this->client->update($parameters);

        $requestParameters = explode('&', parse_url(self::$requestUrl, PHP_URL_QUERY));

        $this->assertContains('commit=true', $requestParameters);
    }

    public function testUpdateRequestIsSentToSolrViaPost()
    {
        $parameters = ['foo' => 'bar'];
        $this->client->update($parameters);

        $this->assertArrayHasKey(CURLOPT_POST, self::$curlOptionsSet);
        $this->assertTrue(self::$curlOptionsSet[CURLOPT_POST]);

        $this->assertArrayHasKey(CURLOPT_POSTFIELDS, self::$curlOptionsSet);
        $this->assertSame(json_encode($parameters), self::$curlOptionsSet[CURLOPT_POSTFIELDS]);
    }

    public function testExceptionIsThrownIfSolrIsNotAccessibleWithUpdateCall()
    {
        self::$returnType = 'html';

        $this->expectException(SolrConnectionException::class);
        $this->expectExceptionMessage('Error 404 Not Found');

        $parameters = [];
        $this->client->update($parameters);
    }

    public function testExceptionIsThrownIfSolrIsNotRunning()
    {
        self::$returnType = 'bool';

        $this->expectException(SolrConnectionException::class);

        $parameters = [];
        $this->client->update($parameters);
    }

    public function testSuccessfulUpdateRequestReturnsAnArray()
    {
        $parameters = [];
        $result = $this->client->update($parameters);

        $this->assertInternalType('array', $result);
    }

    public function testSelectRequestHasSelectServlet()
    {
        $parameters = [];
        $this->client->select($parameters);

        $path = parse_url(self::$requestUrl, PHP_URL_PATH);
        $lastToken = preg_replace('/.*\//', '', $path);

        $this->assertSame(SolrHttpClient::SEARCH_SERVLET, $lastToken);
    }

    public function testSelectRequestIsSentToSolrViaGet()
    {
        $parameters = ['foo' => 'bar', 'baz' => 'qux'];
        $this->client->select($parameters);

        $this->assertArrayHasKey(CURLOPT_POST, self::$curlOptionsSet);
        $this->assertFalse(self::$curlOptionsSet[CURLOPT_POST]);

        $requestParameters = explode('&', parse_url(self::$requestUrl, PHP_URL_QUERY));

        foreach ($parameters as $key => $value) {
            $this->assertContains($key . '=' . $value, $requestParameters);
        }
    }

    public function testExceptionIsThrownIfSolrDoesNotReturnValidJsonForSelectRequest()
    {
        self::$returnType = 'html';

        $this->expectException(SolrConnectionException::class);
        $this->expectExceptionMessage('Error 404 Not Found');

        $parameters = [];
        $this->client->select($parameters);
    }

    public function testSuccessfulSelectRequestReturnsAnArray()
    {
        $parameters = [];
        $result = $this->client->select($parameters);

        $this->assertInternalType('array', $result);
    }
}

/**
 * @param string $url
 * @return resource
 */
function curl_init(string $url)
{
    CurlSolrHttpClientTest::trackRequestUrl($url);
    return \curl_init($url);
}

/**
 * @param resource $handle
 * @param int $option
 * @param mixed $value
 */
function curl_setopt($handle, int $option, $value)
{
    CurlSolrHttpClientTest::trackCurlOptionSet($option, $value);
}

/**
 * @param resource $handle
 * @return string|boolean
 */
function curl_exec($handle)
{
    if (CurlSolrHttpClientTest::getReturnType() === 'html') {
        return '<html><title>Error 404 Not Found</title><body></body></html>';
    }

    if (CurlSolrHttpClientTest::getReturnType() === 'bool') {
        return false;
    }

    return json_encode([]);
}
