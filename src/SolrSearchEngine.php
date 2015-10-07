<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\ContextBuilder;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriteria;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentField;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngine;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\UnsupportedSearchCriteriaOperationException;
use LizardsAndPumpkins\Product\ProductId;
use LizardsAndPumpkins\Utils\Clearable;

class SolrSearchEngine implements SearchEngine, Clearable
{
    const UPDATE_SERVLET = 'update';
    const SEARCH_SERVLET = 'select';
    const FIELD_PREFIX = 'lizards_and_pumpkins_field_';
    const CONTEXT_PREFIX = 'lizards_and_pumpkins_context_';

    /**
     * @var string
     */
    private $solrConnectionPath;

    /**
     * @var string[]
     */
    private $searchableFields;

    /**
     * @param string $solrConnectionPath
     * @param string[] $searchableFields
     */
    public function __construct($solrConnectionPath, array $searchableFields)
    {
        $this->solrConnectionPath = $solrConnectionPath;
        $this->searchableFields = $searchableFields;
    }

    /**
     * @param SearchDocumentCollection $documentsCollection
     */
    public function addSearchDocumentCollection(SearchDocumentCollection $documentsCollection)
    {
        $rawDocuments = array_reduce($documentsCollection->getDocuments(), function ($carry, SearchDocument $document) {
            return $carry . $this->formatQueryFieldXml($document);
        }, '');
        $rawPost = '<add>' . $rawDocuments . '</add>';

        $url = $this->constructUrl(self::UPDATE_SERVLET, ['commit' => 'true']);

        $this->sendRawPostRequest($url, $rawPost);
    }

    /**
     * @param SearchDocument $document
     * @return string
     */
    private function formatQueryFieldXml(SearchDocument $document)
    {
        $rawDocumentFields = $this->buildSearchDocumentFieldsXml($document->getFieldsCollection());
        $rawContextFields = $this->buildContextFieldsXml($document->getContext());
        return '<add>
                    <doc>
                        <field name="product_id">' . addslashes($document->getProductId()) . '</field>
                        ' . $rawDocumentFields . '
                        ' . $rawContextFields . '
                    </doc>
                </add>';
    }

    /**
     * @param SearchDocumentFieldCollection $fieldCollection
     * @return string
     */
    private function buildSearchDocumentFieldsXml(SearchDocumentFieldCollection $fieldCollection)
    {
        return array_reduce($fieldCollection->getFields(), function ($carry, SearchDocumentField $field) {
            $fieldName = addslashes(self::FIELD_PREFIX . $field->getKey());
            return $carry . array_reduce($field->getValues(), function ($carry, $fieldValue) use ($fieldName) {
                return $carry .sprintf('<field name="%s">%s</field>', $fieldName, addslashes($fieldValue));
            }, '');
        }, '');
    }

    /**
     * @param Context $context
     * @return string
     */
    private function buildContextFieldsXml(Context $context)
    {
        return array_reduce($context->getSupportedCodes(), function ($carry, $contextCode) use ($context) {
            $fieldName = addslashes(self::CONTEXT_PREFIX . $contextCode);
            $fieldValue = addslashes($context->getValue($contextCode));
            return $carry . sprintf('<field name="%s">%s</field>', $fieldName, $fieldValue);
        }, '');
    }

    /**
     * @param string $queryString
     * @param Context $context
     * @return SearchDocumentCollection
     */
    public function query($queryString, Context $context)
    {
        $fieldsQueryString = implode(' OR ', array_map(function ($fieldName) use ($queryString) {
            return addslashes(self::FIELD_PREFIX . $fieldName) . ':"' . addslashes($queryString) . '"';
        }, $this->searchableFields));
        $contextQueryString = $this->convertContextIntoQueryString($context);

        $query = '(' . $fieldsQueryString . ') AND ' . $contextQueryString;

        return $this->getSearchDocumentsCollectionMatchingQueryString($query);
    }

    /**
     * @param SearchCriteria $criteria
     * @param Context $context
     * @return SearchDocumentCollection
     */
    public function getSearchDocumentsMatchingCriteria(SearchCriteria $criteria, Context $context)
    {
        $fieldsQueryString = $this->convertCriteriaIntoSolrQueryString($criteria);
        $contextQueryString = $this->convertContextIntoQueryString($context);

        $query = '(' . $fieldsQueryString . ') AND ' . $contextQueryString;

        return $this->getSearchDocumentsCollectionMatchingQueryString($query);
    }

    public function clear()
    {
        $parameters = [
            'stream.body' => '<delete><query>*:*</query></delete>',
            'commit'      => 'true'
        ];
        $url = $this->constructUrl(self::UPDATE_SERVLET, $parameters);
        $this->sendRequest($url);
    }

    /**
     * @param Context $context
     * @return string
     */
    private function convertContextIntoQueryString(Context $context)
    {
        return implode(' AND ', array_map(function ($contextCode) use ($context) {
            $fieldName = addslashes(self::CONTEXT_PREFIX . $contextCode);
            $fieldValue = addslashes($context->getValue($contextCode));
            return sprintf('((-%1$s:[* TO *] AND *:*) OR %1$s:"%2$s")', $fieldName, $fieldValue);
        }, $context->getSupportedCodes()));
    }

    /**
     * @param SearchCriteria $criteria
     * @return string
     */
    private function convertCriteriaIntoSolrQueryString(SearchCriteria $criteria)
    {
        $criteriaJson = json_encode($criteria);
        $criteriaArray = json_decode($criteriaJson, true);

        return $this->createSolrQueryStringFromCriteriaArray($criteriaArray);
    }

    /**
     * @param mixed[] $criteria
     * @return string
     */
    private function createSolrQueryStringFromCriteriaArray(array $criteria)
    {
        if (isset($criteria['condition'])) {
            $subCriteriaQueries = array_map([$this, 'createSolrQueryStringFromCriteriaArray'], $criteria['criteria']);
            $glue = ' ' . strtoupper($criteria['condition']) . ' ';
            return '(' . implode($glue, $subCriteriaQueries) . ')';
        }

        return $this->createPrimitiveOperationQueryString($criteria);
    }

    /**
     * @param string[] $criteria
     * @return string
     */
    private function createPrimitiveOperationQueryString(array $criteria)
    {
        $fieldName = addslashes(self::FIELD_PREFIX . $criteria['fieldName']);
        $fieldValue = addslashes($criteria['fieldValue']);

        if ('Equal' === $criteria['operation']) {
            return sprintf('%s:"%s"', $fieldName, $fieldValue);
        }

        if ('NotEqual' === $criteria['operation']) {
            return sprintf('(-%s:"%s" AND *:*)', $fieldName, $fieldValue);
        }

        if ('LessThan' === $criteria['operation']) {
            return sprintf('(%1$s:[* TO %2$s] AND -%1$s:%2$s)', $fieldName, $fieldValue);
        }

        if ('LessOrEqualThan' === $criteria['operation']) {
            return sprintf('%s:[* TO %s]', $fieldName, $fieldValue);
        }

        if ('GreaterThan' === $criteria['operation']) {
            return sprintf('(%1$s:[%2$s TO *] AND -%1$s:%2$s)', $fieldName, $fieldValue);
        }

        if ('GreaterOrEqualThan' === $criteria['operation']) {
            return sprintf('%s:[%s TO *]', $fieldName, $fieldValue);
        }

        throw new UnsupportedSearchCriteriaOperationException(
            sprintf('Unsupported criterion operation "%s".', $criteria['operation'])
        );
    }

    /**
     * @param string $queryString
     * @return SearchDocumentCollection
     */
    private function getSearchDocumentsCollectionMatchingQueryString($queryString)
    {
        $numberOfRowsToReturn = 10000000;
        $parameters = [
            'q'    => $queryString,
            'rows' => $numberOfRowsToReturn
        ];
        $url = $this->constructUrl(self::SEARCH_SERVLET, $parameters);

        $response = $this->sendRequest($url);
        $responseDocuments = isset($response['response']) && isset($response['response']['docs']) ?
            $response['response']['docs'] :
            [];

        return $this->createSearchDocumentsFromRawResponseDocuments($responseDocuments);
    }

    /**
     * @param mixed[] $responseDocuments
     * @return SearchDocumentCollection
     */
    private function createSearchDocumentsFromRawResponseDocuments(array $responseDocuments)
    {
        if (empty($responseDocuments)) {
            return new SearchDocumentCollection;
        }

        $searchDocuments = array_map(function (array $document) {
            $searchDocumentFieldsCollection = $this->createSearchDocumentFieldsCollectionFromDocumentData($document);
            $context = $this->createContextFromDocumentData($document);
            $productId = ProductId::fromString($document['product_id']);

            return new SearchDocument($searchDocumentFieldsCollection, $context, $productId);
        }, $responseDocuments);

        return new SearchDocumentCollection(...$searchDocuments);
    }

    /**
     * @param string[] $documentData
     * @return SearchDocumentFieldCollection
     */
    private function createSearchDocumentFieldsCollectionFromDocumentData(array $documentData)
    {
        $searchDocumentFieldsArray = $this->extractSearchDocumentFieldsFromDocumentData($documentData);
        return SearchDocumentFieldCollection::fromArray($searchDocumentFieldsArray);
    }

    /**
     * @param string[] $documentData
     * @return string[]
     */
    private function extractSearchDocumentFieldsFromDocumentData(array $documentData)
    {
        $searchDocumentFieldsArray = [];
        foreach ($documentData as $fieldName => $fieldValue) {
            if (preg_match('/^' . self::FIELD_PREFIX . '(.*)/', $fieldName, $matches)) {
                $searchDocumentFieldsArray[$matches[1]] = $fieldValue;
            }
        }

        return $searchDocumentFieldsArray;
    }

    /**
     * @param string $url
     * @return mixed
     */
    private function sendRequest($url)
    {
        $curlHandle = $this->prepareCurlHandle($url);
        curl_setopt($curlHandle, CURLOPT_POST, false);

        return $this->executeCurlRequest($curlHandle);
    }

    /**
     * @param string $url
     * @param string $rawPostFields
     * @return mixed
     */
    private function sendRawPostRequest($url, $rawPostFields)
    {
        $curlHandle = $this->prepareCurlHandle($url);
        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $rawPostFields);

        return $this->executeCurlRequest($curlHandle);
    }

    /**
     * @param string $url
     * @return resource
     */
    private function prepareCurlHandle($url)
    {
        $curlHandle = curl_init($url);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-type: text/xml']);

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
        $this->validateSolrResponse($responseJson);

        return $response;
    }

    /**
     * @param string[] $documentData
     * @return Context
     */
    private function createContextFromDocumentData(array $documentData)
    {
        $contextDataSet = $this->extractContextDataSetFromDocumentData($documentData);
        return ContextBuilder::rehydrateContext($contextDataSet);
    }

    /**
     * @param string[] $documentData
     * @return string[]
     */
    private function extractContextDataSetFromDocumentData(array $documentData)
    {
        $contextDataSource = [];
        foreach ($documentData as $fieldName => $fieldValue) {
            if (preg_match('/^' . self::CONTEXT_PREFIX . '(.*)/', $fieldName, $matches)) {
                $contextDataSource[$matches[1]] = $fieldValue;
            }
        }

        return $contextDataSource;
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
        $queryString = preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', $queryString);

        return $this->solrConnectionPath . $servlet . '?' . $queryString;
    }

    /**
     * @param string $rawResponse
     */
    private function validateSolrResponse($rawResponse)
    {
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = preg_replace('/.*<title>|<\/title>.*/ism', '', $rawResponse);
            throw new SolrException($errorMessage);
        }
    }
}
