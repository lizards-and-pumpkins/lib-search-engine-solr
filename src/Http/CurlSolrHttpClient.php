<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http;

use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\Exception\SolrConnectionException;

class CurlSolrHttpClient implements SolrHttpClient
{
    /**
     * @var string
     */
    private $solrConnectionPath;

    /**
     * @param string $solrConnectionPath
     */
    public function __construct($solrConnectionPath)
    {
        $this->solrConnectionPath = $solrConnectionPath;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
    private function constructUrl($servlet, array $requestParameters)
    {
        $defaultParameters = ['wt' => 'json'];
        $parameters = array_merge($defaultParameters, $requestParameters);

        $queryString = http_build_query($parameters);

        return $this->solrConnectionPath . $servlet . '?' . $queryString;
    }

    /**
     * @param string $url
     * @return resource
     */
    private function createCurlHandle($url)
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
        $response = json_decode($responseJson, true);
        $this->validateResponseType($responseJson);

        return $response;
    }

    /**
     * @param string $rawResponse
     */
    private function validateResponseType($rawResponse)
    {
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = preg_replace('/.*<title>|<\/title>.*/ism', '', $rawResponse);
            throw new SolrConnectionException($errorMessage);
        }
    }
}
