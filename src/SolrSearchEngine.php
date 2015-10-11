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
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperator;
use LizardsAndPumpkins\Product\ProductId;
use LizardsAndPumpkins\Utils\Clearable;

class SolrSearchEngine implements SearchEngine, Clearable
{
    const UPDATE_SERVLET = 'update';
    const SEARCH_SERVLET = 'select';
    const FIELD_PREFIX = 'lizards_and_pumpkins_field_';
    const DOCUMENT_ID_FIELD_NAME = 'id';
    const PRODUCT_ID_FIELD_NAME = 'product_id';
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
        $url = $this->constructUrl(self::UPDATE_SERVLET, ['commit' => 'true']);
        $documents = array_map(function (SearchDocument $document) {
            $context = $document->getContext();
            return array_merge(
                [
                    self::DOCUMENT_ID_FIELD_NAME => $document->getProductId() . '_' . $context->toString(),
                    self::PRODUCT_ID_FIELD_NAME => (string) $document->getProductId()
                ],
                $this->getSearchDocumentFields($document->getFieldsCollection()),
                $this->getContextFields($context)
            );
        }, $documentsCollection->getDocuments());

        $this->sendRawPostRequest($url, json_encode($documents));
    }

    /**
     * @param SearchDocumentFieldCollection $fieldCollection
     * @return array[]
     */
    private function getSearchDocumentFields(SearchDocumentFieldCollection $fieldCollection)
    {
        return array_reduce($fieldCollection->getFields(), function ($carry, SearchDocumentField $field) {
            return array_merge([self::FIELD_PREFIX . $field->getKey() => $field->getValues()], $carry);
        }, []);
    }

    /**
     * @param Context $context
     * @return string[]
     */
    private function getContextFields(Context $context)
    {
        return array_reduce($context->getSupportedCodes(), function ($carry, $contextCode) use ($context) {
            return array_merge([self::CONTEXT_PREFIX . $contextCode => $context->getValue($contextCode)], $carry);
        }, []);
    }

    /**
     * @param string $queryString
     * @param Context $context
     * @return SearchDocumentCollection
     */
    public function query($queryString, Context $context)
    {
        $fieldsQueryString = implode(' OR ', array_map(function ($fieldName) use ($queryString) {
            return urlencode(self::FIELD_PREFIX . $fieldName) . ':"' . urlencode($queryString) . '"';
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
        $url = $this->constructUrl(self::UPDATE_SERVLET, ['commit' => 'true']);
        $request = ['delete' => ['query' => '*:*']];

        $this->sendRawPostRequest($url, json_encode($request));
    }

    /**
     * @param Context $context
     * @return string
     */
    private function convertContextIntoQueryString(Context $context)
    {
        return implode(' AND ', array_map(function ($contextCode) use ($context) {
            $fieldName = urlencode(self::CONTEXT_PREFIX . $contextCode);
            $fieldValue = urlencode($context->getValue($contextCode));
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
        $fieldName = self::FIELD_PREFIX . $criteria['fieldName'];
        $fieldValue = $criteria['fieldValue'];
        $operator = $this->getSolrOperator($criteria['operation']);

        return $operator->getFormattedQueryString($fieldName, $fieldValue);
    }

    /**
     * @param string $operation
     * @return SolrQueryOperator
     */
    private function getSolrOperator($operation)
    {
        $className = __NAMESPACE__ . '\\Operator\\SolrQueryOperator' . $operation;

        if (!class_exists($className)) {
            throw new UnsupportedSearchCriteriaOperationException(
                sprintf('Unsupported criterion operation "%s".', $operation)
            );
        }

        return new $className;
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
            $productId = ProductId::fromString(array_shift($document[self::PRODUCT_ID_FIELD_NAME]));

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
        $searchDocumentFieldsArray = $this->getSolrDocumentFieldsStartingWith($documentData, self::FIELD_PREFIX);
        return SearchDocumentFieldCollection::fromArray($searchDocumentFieldsArray);
    }

    /**
     * @param string[] $documentData
     * @return Context
     */
    private function createContextFromDocumentData(array $documentData)
    {
        $contextSolrDocumentFields = $this->getSolrDocumentFieldsStartingWith($documentData, self::CONTEXT_PREFIX);
        $contextDataSet = array_map('array_shift', $contextSolrDocumentFields);
        return ContextBuilder::rehydrateContext($contextDataSet);
    }

    /**
     * @param string[] $documentData
     * @param string $prefix
     * @return string[]
     */
    private function getSolrDocumentFieldsStartingWith(array $documentData, $prefix)
    {
        $documentFieldsArray = [];
        foreach ($documentData as $fieldName => $fieldValue) {
            if (preg_match('/^' . $prefix . '(.*)/', $fieldName, $matches)) {
                $documentFieldsArray[$matches[1]] = $fieldValue;
            }
        }

        return $documentFieldsArray;
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
        $this->validateSolrResponse($response, $responseJson);

        return $response;
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
        $queryString = $this->buildSolrQueryString($parameters);

        return $this->solrConnectionPath . $servlet . '?' . $queryString;
    }

    /**
     * @param string[] $parameters
     * @return string
     */
    private function buildSolrQueryString(array $parameters)
    {
        $replaceSolrArrayWithPlainFieldPattern = '/%5B(?:[0-9]|[1-9][0-9]+)%5D=/';
        $queryString = http_build_query($parameters);

        return preg_replace($replaceSolrArrayWithPlainFieldPattern, '=', $queryString);
    }

    /**
     * @param mixed[]|null $decodedResponse
     * @param string $rawResponse
     */
    private function validateSolrResponse($decodedResponse, $rawResponse)
    {
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = preg_replace('/.*<title>|<\/title>.*/ism', '', $rawResponse);
            throw new SolrException($errorMessage);
        }

        if (is_array($decodedResponse) && isset($decodedResponse['error'])) {
            throw new SolrException($decodedResponse['error']['msg']);
        }
    }
}
