<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http;

use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\Exception\SolrConnectionException;

class CurlSolrHttpClient implements SolrHttpClient
{
    /**
     * @var string
     */
    private $solrConnectionPath;

    public function __construct(string $solrConnectionPath)
    {
        $this->solrConnectionPath = $solrConnectionPath;
    }

    /**
     * @param mixed[] $parameters
     * @return mixed
     */
    public function update(array $parameters)
    {
        $urlGetParameters = ['commit' => 'true'];
        $url = $this->constructUrl(SolrHttpClient::UPDATE_SERVLET, $urlGetParameters);

        $curlHandle = $this->createCurlHandle($url);
        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, json_encode($parameters));

        return $this->executeCurlRequest($curlHandle);
    }

    /**
     * @param mixed[] $parameters
     * @return mixed
     */
    public function select(array $parameters)
    {
        $url = $this->constructUrl(SolrHttpClient::SEARCH_SERVLET, $parameters);

        $curlHandle = $this->createCurlHandle($url);
        curl_setopt($curlHandle, CURLOPT_POST, false);

        return $this->executeCurlRequest($curlHandle);
    }

    /**
     * @param string $servlet
     * @param string[] $requestParameters
     * @return string
     */
    private function constructUrl(string $servlet, array $requestParameters) : string
    {
        $defaultParameters = ['wt' => 'json'];
        $parameters = array_merge($defaultParameters, $requestParameters);

        $queryString = http_build_query($parameters);
        $arraySafeQueryString = $this->replaceSolrArrayWithPlainField($queryString);

        return $this->solrConnectionPath . $servlet . '?' . $arraySafeQueryString;
    }

    private function replaceSolrArrayWithPlainField(string $queryString) : string
    {
        return preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', $queryString);
    }

    /**
     * @param string $url
     * @return resource
     */
    private function createCurlHandle(string $url)
    {
        $curlHandle = curl_init($url);

        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-type: application/json']);

        return $curlHandle;
    }

    /**
     * @param resource $curlHandle
     * @return mixed
     */
    private function executeCurlRequest($curlHandle)
    {
        $responseJson = curl_exec($curlHandle);

        if (false === $responseJson) {
            throw new SolrConnectionException(curl_error($curlHandle));
        }

        $response = json_decode($responseJson, true);
        $this->validateResponseType($responseJson);

        return $response;
    }

    private function validateResponseType(string $rawResponse)
    {
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = preg_replace('/.*<title>|<\/title>.*/ism', '', $rawResponse);
            throw new SolrConnectionException($errorMessage);
        }
    }
}
