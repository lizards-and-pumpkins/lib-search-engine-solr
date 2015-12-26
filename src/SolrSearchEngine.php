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
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriteria;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngine;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngineResponse;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\SolrHttpClient;
use LizardsAndPumpkins\Product\AttributeCode;
use LizardsAndPumpkins\Product\ProductId;
use LizardsAndPumpkins\Utils\Clearable;

class SolrSearchEngine implements SearchEngine, Clearable
{
    const DOCUMENT_ID_FIELD_NAME = 'id';
    const PRODUCT_ID_FIELD_NAME = 'product_id';
    const TOKENIZED_FIELD_SUFFIX = '_tokenized';

    /**
     * @var SolrHttpClient
     */
    private $client;

    /**
     * @var string[]
     */
    private $searchableFields;

    /**
     * @var FacetFieldTransformationRegistry
     */
    private $facetFieldTransformationRegistry;

    /**
     * @param SolrHttpClient $client
     * @param string[] $searchableFields
     * @param FacetFieldTransformationRegistry $facetFieldTransformationRegistry
     */
    public function __construct(
        SolrHttpClient $client,
        array $searchableFields,
        FacetFieldTransformationRegistry $facetFieldTransformationRegistry
    ) {
        $this->client = $client;
        $this->searchableFields = $searchableFields;
        $this->facetFieldTransformationRegistry = $facetFieldTransformationRegistry;
    }

    public function addSearchDocumentCollection(SearchDocumentCollection $documentsCollection)
    {
        (new SolrWriter($this->client))->addSearchDocumentsCollectionToSolr($documentsCollection);
    }

    /**
     * {@inheritdoc}
     */
    public function query(
        SearchCriteria $criteria,
        array $filterSelection,
        Context $context,
        FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult,
        $rowsPerPage,
        $pageNumber,
        SortOrderConfig $sortOrderConfig
    ) {
        $query = SolrQuery::create($criteria, $context, $rowsPerPage, $pageNumber, $sortOrderConfig);
        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $facetFiltersToIncludeInResult,
            $filterSelection,
            $this->facetFieldTransformationRegistry
        );
        $response = $this->getSearchDocumentMatchingQuery($query, $solrFacetFilterRequest);

        $totalNumberOfResults = $this->getTotalNumberOfResultsFromSolrResponse($response);
        $matchingProductIds = $this->getMatchingProductIdsFromSolrResponse($response);
        $facetFieldsCollection = $this->getFacetFieldCollectionFromSolrResponse(
            $response,
            $query,
            $filterSelection,
            $facetFiltersToIncludeInResult
        );

        return new SearchEngineResponse($facetFieldsCollection, $totalNumberOfResults, ...$matchingProductIds);
    }

    public function clear()
    {
        (new SolrWriter($this->client))->deleteAllDocuments();
    }

    /**
     * @param mixed[] $response
     */
    private function validateSolrResponse(array $response)
    {
        if (isset($response['error'])) {
            throw new SolrException($response['error']['msg']);
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
     * @param array[] $response
     * @return int
     */
    private function getTotalNumberOfResultsFromSolrResponse(array $response)
    {
        if (! isset($response['response']['numFound'])) {
            return 0;
        }

        return $response['response']['numFound'];
    }

    /**
     * @param array[] $response
     * @param SolrQuery $query
     * @param array[] $filterSelection
     * @param FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult
     * @return FacetFieldCollection
     */
    private function getFacetFieldCollectionFromSolrResponse(
        array $response,
        SolrQuery $query,
        array $filterSelection,
        FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult
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
            $facetFiltersToIncludeInResult
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
     * @param SolrQuery $query
     * @param FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult
     * @return FacetField[]
     */
    private function getSelectedFacetFieldsFromSolrFacetFields(
        array $filterSelection,
        SolrQuery $query,
        FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult
    ) {
        $selectedAttributeCodes = array_keys($filterSelection);
        $facetFields = [];

        foreach ($selectedAttributeCodes as $attributeCodeString) {
            $selectedFiltersExceptCurrentOne = array_diff_key($filterSelection, [$attributeCodeString => []]);
            $solrFacetFilterRequest = new SolrFacetFilterRequest(
                $facetFiltersToIncludeInResult,
                $selectedFiltersExceptCurrentOne,
                $this->facetFieldTransformationRegistry
            );
            $response = $this->getSearchDocumentMatchingQuery($query, $solrFacetFilterRequest);
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
     * @param array[] $filterSelection
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
     * @param SolrQuery $query
     * @param SolrFacetFilterRequest $solrFacetFilterRequest
     * @return mixed
     */
    private function getSearchDocumentMatchingQuery(SolrQuery $query, SolrFacetFilterRequest $solrFacetFilterRequest)
    {
        $queryParameters = $query->toArray();
        $facetParameters = $solrFacetFilterRequest->toArray();

        $response = $this->client->select(array_merge($queryParameters, $facetParameters));
        $this->validateSolrResponse($response);

        return $response;
    }

    /**
     * @param int[] $facetQueries
     * @param string[] $attributeCodes
     * @return array[]
     */
    private function buildRawFacetQueriesForGivenAttributes(array $facetQueries, array $attributeCodes)
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
            $attributeValueCounts = $rawFacetQueries[$attributeCodeString];
            $facetFieldValues = $this->createFacetFieldValues($attributeValueCounts);
            return new FacetField($attributeCode, ...$facetFieldValues);
        }, array_keys($rawFacetQueries));
    }

    /**
     * @param int[] $attributeValueCounts
     * @return FacetFieldValue[]
     */
    private function createFacetFieldValues(array $attributeValueCounts)
    {
        return array_map(function ($value) use ($attributeValueCounts) {
            $count = $attributeValueCounts[$value];
            return FacetFieldValue::create((string) $value, $count);
        }, array_keys($attributeValueCounts));
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
