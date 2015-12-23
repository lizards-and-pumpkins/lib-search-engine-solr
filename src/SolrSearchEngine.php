<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderConfig;
use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\DataPool\SearchEngine\Exception\NoFacetFieldTransformationRegisteredException;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldValue;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRange;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequest;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequestField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequestRangedField;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriteria;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentField;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngine;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngineResponse;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\UnsupportedSearchCriteriaOperationException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperator;
use LizardsAndPumpkins\Product\AttributeCode;
use LizardsAndPumpkins\Product\ProductId;
use LizardsAndPumpkins\Utils\Clearable;

class SolrSearchEngine implements SearchEngine, Clearable
{
    const UPDATE_SERVLET = 'update';
    const SEARCH_SERVLET = 'select';
    const DOCUMENT_ID_FIELD_NAME = 'id';
    const PRODUCT_ID_FIELD_NAME = 'product_id';
    const TOKENIZED_FIELD_SUFFIX = '_tokenized';

    /**
     * @var string
     */
    private $solrConnectionPath;

    /**
     * @var string[]
     */
    private $searchableFields;

    /**
     * @var FacetFieldTransformationRegistry
     */
    private $facetFieldTransformationRegistry;

    /**
     * @param string $solrConnectionPath
     * @param string[] $searchableFields
     * @param FacetFieldTransformationRegistry $facetFieldTransformationRegistry
     */
    public function __construct(
        $solrConnectionPath,
        array $searchableFields,
        FacetFieldTransformationRegistry $facetFieldTransformationRegistry
    ) {
        $this->solrConnectionPath = $solrConnectionPath;
        $this->searchableFields = $searchableFields;
        $this->facetFieldTransformationRegistry = $facetFieldTransformationRegistry;
    }

    public function addSearchDocumentCollection(SearchDocumentCollection $documentsCollection)
    {
        $url = $this->constructUrl(self::UPDATE_SERVLET, ['commit' => 'true']);
        $documents = array_map([$this, 'convertSearchDocumentToArray'], iterator_to_array($documentsCollection));

        $this->sendRawPostRequest($url, json_encode($documents));
    }

    /**
     * @param SearchDocumentFieldCollection $fieldCollection
     * @return array[]
     */
    private function getSearchDocumentFields(SearchDocumentFieldCollection $fieldCollection)
    {
        return array_reduce($fieldCollection->getFields(), function ($carry, SearchDocumentField $field) {
            return array_merge([$field->getKey() => $field->getValues()], $carry);
        }, []);
    }

    /**
     * @param Context $context
     * @return string[]
     */
    private function getContextFields(Context $context)
    {
        return array_reduce($context->getSupportedCodes(), function ($carry, $contextCode) use ($context) {
            return array_merge([$contextCode => $context->getValue($contextCode)], $carry);
        }, []);
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchDocumentsMatchingCriteria(
        SearchCriteria $criteria,
        array $filterSelection,
        Context $context,
        FacetFilterRequest $facetFilterRequest,
        $rowsPerPage,
        $pageNumber,
        SortOrderConfig $sortOrderConfig
    ) {
        $fieldsQueryString = $this->convertCriteriaIntoSolrQueryString($criteria);
        $contextQueryString = $this->convertContextIntoQueryString($context);

        $query = '(' . $fieldsQueryString . ') AND ' . $contextQueryString;

        $response = $this->getSearchDocumentMatchingQuery(
            $query,
            $filterSelection,
            $facetFilterRequest,
            $rowsPerPage,
            $pageNumber,
            $sortOrderConfig
        );

        $totalNumberOfResults = $this->getTotalNumberOfResultsFromSolrResponse($response);
        $matchingProductIds = $this->getMatchingProductIdsFromSolrResponse($response);
        $facetFieldsCollection = $this->getFacetFieldCollectionFromSolrResponse(
            $response,
            $query,
            $filterSelection,
            $facetFilterRequest,
            $rowsPerPage,
            $pageNumber,
            $sortOrderConfig
        );

        return new SearchEngineResponse($facetFieldsCollection, $totalNumberOfResults, ...$matchingProductIds);
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
            $fieldName = urlencode($contextCode);
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
        $fieldName = $criteria['fieldName'];
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
     * @param mixed[] $responseDocuments
     * @return ProductId[]
     */
    private function getProductIdsOfMatchingDocuments(array $responseDocuments)
    {
        return array_map(function (array $document) {
            return ProductId::fromString($document[self::PRODUCT_ID_FIELD_NAME]);
        }, $responseDocuments);
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

    /**
     * @param array[] $response
     * @return ProductId[]
     */
    private function getMatchingProductIdsFromSolrResponse(array $response)
    {
        if (! isset($response['response']) || ! isset($response['response']['docs'])) {
            return [];
        }

        return $this->getProductIdsOfMatchingDocuments($response['response']['docs']);
    }

    /**
     * @param array[] $response
     * @return int
     */
    private function getTotalNumberOfResultsFromSolrResponse(array $response)
    {
        if(! isset($response['response']['numFound'])) {
            return 0;
        }

        return $response['response']['numFound'];
    }

    /**
     * @param FacetFilterRequest $facetFilterRequest
     * @param string[] $filterSelection
     * @return mixed[]
     */
    private function getFacetRequestParameters(FacetFilterRequest $facetFilterRequest, array $filterSelection)
    {
        $fields = $facetFilterRequest->getFields();

        if (count($fields) === 0) {
            return [];
        }

        $parameters = [
            'facet' => 'on',
            'facet.mincount' => 1
        ];

        $facetFields = $this->getFacetFields(...$fields);

        if (count($facetFields) > 0) {
            $parameters['facet.field'] = $facetFields;
        }

        $facetQueries = $this->getFacetQueries(...$fields);

        if (count($facetQueries) > 0) {
            $parameters['facet.query'] = $facetQueries;
        }

        if (count($filterSelection) > 0) {
            $parameters['fq'] = $this->getSelectedFacetQueries($filterSelection);
        }

        return $parameters;
    }

    /**
     * @param FacetFilterRequestField[] ...$fields
     * @return string[]
     */
    private function getFacetFields(FacetFilterRequestField ...$fields)
    {
        return array_reduce($fields, function(array $carry, FacetFilterRequestField $field) {
            if ($field->isRanged()) {
                return $carry;
            }
            return array_merge($carry, [(string) $field->getAttributeCode()]);
        }, []);
    }

    /**
     * @param FacetFilterRequestField[] ...$fields
     * @return string[]
     */
    private function getFacetQueries(FacetFilterRequestField ...$fields)
    {
        return array_reduce($fields, function(array $carry, FacetFilterRequestField $field) {
            if (!$field->isRanged()) {
                return $carry;
            }

            return array_merge($carry, $this->getRangedFieldRanges($field));
        }, []);
    }

    /**
     * @param FacetFilterRequestRangedField $field
     * @return string[]
     */
    private function getRangedFieldRanges(FacetFilterRequestRangedField $field)
    {
        return array_reduce($field->getRanges(), function (array $carry, FacetFilterRange $range) use ($field) {
            $from = null === $range->from() ?
                '*' :
                $range->from();
            $to = null === $range->to() ?
                '*' :
                $range->to();

            return array_merge($carry, [sprintf('%s:[%s TO %s]', $field->getAttributeCode(), $from, $to)]);
        }, []);
    }

    /**
     * @param string[] $filterSelection
     * @return string[]
     */
    private function getSelectedFacetQueries(array $filterSelection)
    {
        return array_reduce(array_keys($filterSelection), function (array $carry, $filterCode) use ($filterSelection) {
            if (count($filterSelection[$filterCode]) > 0) {
                $carry[] = $this->getFormattedFacetQueryValues($filterCode, $filterSelection[$filterCode]);
            }
            return $carry;
        }, []);
    }

    /**
     * @param string $filterCode
     * @param string[] $filterValues
     * @return string
     */
    private function getFormattedFacetQueryValues($filterCode, array $filterValues)
    {
        if ($this->facetFieldTransformationRegistry->hasTransformationForCode($filterCode)) {
            $transformation = $this->facetFieldTransformationRegistry->getTransformationByCode($filterCode);
            $formattedRanges = array_map(function($filterValue) use ($transformation) {
                $facetFilterRange = $transformation->decode($filterValue);
                return sprintf('[%s TO %s]', $facetFilterRange->from(), $facetFilterRange->to());
            }, $filterValues);
            return sprintf('%s:(%s)', $filterCode, implode(' OR ', $formattedRanges));
        }

        return sprintf('%s:("%s")', $filterCode, implode('" OR "', $filterValues));
    }

    /**
     * @param array[] $response
     * @param string $query
     * @param array[] $filterSelection
     * @param FacetFilterRequest $facetFilterRequest
     * @param int $rowsPerPage
     * @param int $pageNumber
     * @param SortOrderConfig $sortOrderConfig
     * @return FacetFieldCollection
     */
    private function getFacetFieldCollectionFromSolrResponse(
        array $response,
        $query,
        array $filterSelection,
        FacetFilterRequest $facetFilterRequest,
        $rowsPerPage,
        $pageNumber,
        SortOrderConfig $sortOrderConfig
    ) {
        if (!isset($response['facet_counts']['facet_fields']) || !isset($response['facet_counts']['facet_queries'])) {
            return new FacetFieldCollection();
        }

        $facetFields = $this->getNonSelectedFacetFieldsFromSolrFacetFields(
            $response['facet_counts']['facet_fields'],
            $filterSelection
        );
        $facetQueries = $this->getNonSelectedFacetFieldsFromSolrFacetQueries(
            $response['facet_counts']['facet_queries'],
            $filterSelection
        );
        $selectedFacetFieldsSiblings = $this->getSelectedFacetFieldsFromSolrFacetFields(
            $filterSelection,
            $query,
            $facetFilterRequest,
            $rowsPerPage,
            $pageNumber,
            $sortOrderConfig
        );

        return new FacetFieldCollection(...$facetFields, ...$facetQueries, ...$selectedFacetFieldsSiblings);
    }

    /**
     * @param array[] $facetFieldsArray
     * @param array[] $filterSelection
     * @return FacetField[]
     */
    private function getNonSelectedFacetFieldsFromSolrFacetFields(array $facetFieldsArray, array $filterSelection)
    {
        $unselectedAttributeCodes = array_diff(array_keys($facetFieldsArray), array_keys($filterSelection));

        return array_map(function ($attributeCodeString) use ($facetFieldsArray) {
            return $this->createFacetField($attributeCodeString, $facetFieldsArray[$attributeCodeString]);
        }, $unselectedAttributeCodes);
    }

    /**
     * @param array[] $filterSelection
     * @param string $query
     * @param FacetFilterRequest $facetFilterRequest
     * @param int $rowsPerPage
     * @param int $pageNumber
     * @param SortOrderConfig $sortOrderConfig
     * @return FacetField[]
     */
    private function getSelectedFacetFieldsFromSolrFacetFields(
        array $filterSelection,
        $query,
        FacetFilterRequest $facetFilterRequest,
        $rowsPerPage,
        $pageNumber,
        SortOrderConfig $sortOrderConfig
    ) {
        $selectedAttributeCodes = array_keys($filterSelection);
        $facetFields = [];

        foreach ($selectedAttributeCodes as $attributeCodeString) {
            $selectedFiltersExceptCurrentOne = array_diff_key($filterSelection, [$attributeCodeString => []]);
            $response = $this->getSearchDocumentMatchingQuery(
                $query,
                $selectedFiltersExceptCurrentOne,
                $facetFilterRequest,
                $rowsPerPage,
                $pageNumber,
                $sortOrderConfig
            );
            $facetFieldsSiblings = $this->getNonSelectedFacetFieldsFromSolrFacetFields(
                $response['facet_counts']['facet_fields'],
                $selectedFiltersExceptCurrentOne
            );
            $facetQueriesSiblings = $this->getNonSelectedFacetFieldsFromSolrFacetQueries(
                $response['facet_counts']['facet_queries'],
                $selectedFiltersExceptCurrentOne
            );
            $facetFields = array_merge($facetFields, $facetFieldsSiblings, $facetQueriesSiblings);
        }

        return $facetFields;
    }

    /**
     * @param string $attributeCodeString
     * @param mixed[] $facetFieldsValues
     * @return FacetField
     */
    private function createFacetField($attributeCodeString, array $facetFieldsValues)
    {
        $attributeCode = AttributeCode::fromString($attributeCodeString);
        $facetFieldValues = array_map(function (array $fieldData) {
            return FacetFieldValue::create($fieldData[0], $fieldData[1]);
        }, array_chunk($facetFieldsValues, 2));

        return new FacetField($attributeCode, ...$facetFieldValues);
    }

    /**
     * @param array[] $facetQueries
     * @param array $filterSelection
     * @return FacetField[]
     */
    private function getNonSelectedFacetFieldsFromSolrFacetQueries(array $facetQueries, array $filterSelection)
    {
        $selectedAttributeCodes = array_keys($filterSelection);
        $rawFacetQueries = $this->buildRawFacetQueriesForGivenAttributes($facetQueries, $selectedAttributeCodes);

        return $this->buildFacetFieldValuesFromRawFacetQueries($rawFacetQueries);
    }

    /**
     * @param string $attributeCode
     * @param string $from
     * @param string $to
     * @return string
     */
    private function getEncodedFilterRange($attributeCode, $from, $to)
    {
        if (!$this->facetFieldTransformationRegistry->hasTransformationForCode($attributeCode)) {
            throw new NoFacetFieldTransformationRegisteredException(
                sprintf('No facet field transformation is geristered for "%s" attribute.', $attributeCode)
            );
        }

        $transformation = $this->facetFieldTransformationRegistry->getTransformationByCode($attributeCode);

        $facetFilterRange = FacetFilterRange::create(
            $this->getRangeBoundaryValue($from),
            $this->getRangeBoundaryValue($to)
        );

        return $transformation->encode($facetFilterRange);
    }

    /**
     * @param string $query
     * @param array[] $filterSelection
     * @param FacetFilterRequest $facetFilterRequest
     * @param int $rowsPerPage
     * @param int $pageNumber
     * @param SortOrderConfig $sortOrderConfig
     * @return mixed
     */
    private function getSearchDocumentMatchingQuery(
        $query,
        array $filterSelection,
        FacetFilterRequest $facetFilterRequest,
        $rowsPerPage,
        $pageNumber,
        SortOrderConfig $sortOrderConfig
    ) {
        $parameters = [
            'q'     => $query,
            'rows'  => $rowsPerPage,
            'start' => $pageNumber * $rowsPerPage,
            'sort'  => $sortOrderConfig->getAttributeCode() . ' ' . $sortOrderConfig->getSelectedDirection(),
        ];
        $facetParameters = $this->getFacetRequestParameters($facetFilterRequest, $filterSelection);
        $url = $this->constructUrl(self::SEARCH_SERVLET, array_merge($parameters, $facetParameters));

        return $this->sendRequest($url);
    }

    /**
     * @param SearchDocument $document
     * @return string[]
     */
    private function convertSearchDocumentToArray(SearchDocument $document)
    {
        $context = $document->getContext();

        return array_merge(
            [
                self::DOCUMENT_ID_FIELD_NAME => $document->getProductId() . '_' . $context,
                self::PRODUCT_ID_FIELD_NAME => (string) $document->getProductId()
            ],
            $this->getSearchDocumentFields($document->getFieldsCollection()),
            $this->getContextFields($context)
        );
    }

    /**
     * @param int[] $facetQueries
     * @param $attributeCodes
     * @return array[]
     */
    private function buildRawFacetQueriesForGivenAttributes(array $facetQueries, $attributeCodes)
    {
        $queries = array_keys($facetQueries);

        return array_reduce($queries, function (array $carry, $query) use ($facetQueries, $attributeCodes) {
            preg_match('/^(.*):\[(.*) TO (.*)\]$/', $query, $matches);

            $attributeCode = $matches[1];

            if (in_array($attributeCode, $attributeCodes)) {
                return $carry;
            }

            $value = $this->getEncodedFilterRange($attributeCode, $matches[2], $matches[3]);
            $count = $facetQueries[$query];

            $carry[$attributeCode][$value] = $count;

            return $carry;
        }, []);
    }

    /**
     * @param array[] $rawFacetQueries
     * @return FacetField[]
     */
    private function buildFacetFieldValuesFromRawFacetQueries(array $rawFacetQueries)
    {
        return array_map(function ($attributeCodeString) use ($rawFacetQueries) {
            $attributeCode = AttributeCode::fromString($attributeCodeString);
            $facetFieldValues = array_map(function ($value) use ($attributeCodeString, $rawFacetQueries) {
                $count = $rawFacetQueries[$attributeCodeString][$value];

                return FacetFieldValue::create((string) $value, $count);
            }, array_keys($rawFacetQueries[$attributeCodeString]));

            return new FacetField($attributeCode, ...$facetFieldValues);
        }, array_keys($rawFacetQueries));
    }

    /**
     * @param string $boundary
     * @return string
     */
    private function getRangeBoundaryValue($boundary)
    {
        if ('*' === $boundary) {
            return '';
        }

        return $boundary;
    }
}
