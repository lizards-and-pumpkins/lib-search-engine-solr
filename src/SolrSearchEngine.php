<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderConfig;
use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriteria;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngine;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngineResponse;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\SolrHttpClient;
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

    public function addSearchDocumentCollection(SearchDocumentCollection $collection)
    {
        $documents = array_map([SolrDocumentBuilder::class, 'fromSearchDocument'], iterator_to_array($collection));
        $this->client->update($documents);
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
        $facetFilterRequest = new SolrFacetFilterRequest(
            $facetFiltersToIncludeInResult,
            $filterSelection,
            $this->facetFieldTransformationRegistry
        );
        $response = $this->querySolr($query, $facetFilterRequest);

        $totalNumberOfResults = $response->getTotalNumberOfResults();
        $matchingProductIds = $response->getMatchingProductIds();
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
        $request = ['delete' => ['query' => '*:*']];
        $this->client->update($request);
    }

    /**
     * @param SolrResponse $response
     * @param SolrQuery $query
     * @param array[] $filterSelection
     * @param FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult
     * @return FacetFieldCollection
     */
    private function getFacetFieldCollectionFromSolrResponse(
        SolrResponse $response,
        SolrQuery $query,
        array $filterSelection,
        FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult
    ) {
        $selectedFilterAttributeCodes = array_keys($filterSelection);
        $nonSelectedFacetFields = $response->getNonSelectedFacetFields($selectedFilterAttributeCodes);
        $selectedFacetFields = $this->getSelectedFacetFields($filterSelection, $query, $facetFiltersToIncludeInResult);

        return new FacetFieldCollection(...$nonSelectedFacetFields, ...$selectedFacetFields);
    }

    /**
     * @param array[] $filterSelection
     * @param SolrQuery $query
     * @param FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult
     * @return FacetField[]
     */
    private function getSelectedFacetFields(
        array $filterSelection,
        SolrQuery $query,
        FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult
    ) {
        $selectedAttributeCodes = array_keys($filterSelection);
        $facetFields = [];

        foreach ($selectedAttributeCodes as $attributeCodeString) {
            $selectedFiltersExceptCurrentOne = array_diff_key($filterSelection, [$attributeCodeString => []]);
            $facetFilterRequest = new SolrFacetFilterRequest(
                $facetFiltersToIncludeInResult,
                $selectedFiltersExceptCurrentOne,
                $this->facetFieldTransformationRegistry
            );
            $response = $this->querySolr($query, $facetFilterRequest);
            $facetFieldsSiblings = $response->getNonSelectedFacetFields(array_keys($selectedFiltersExceptCurrentOne));
            $facetFields = array_merge($facetFields, $facetFieldsSiblings);
        }

        return $facetFields;
    }

    /**
     * @param SolrQuery $query
     * @param SolrFacetFilterRequest $facetFilterRequest
     * @return SolrResponse
     */
    private function querySolr(SolrQuery $query, SolrFacetFilterRequest $facetFilterRequest)
    {
        $queryParameters = $query->toArray();
        $facetParameters = $facetFilterRequest->toArray();

        $response = $this->client->select(array_merge($queryParameters, $facetParameters));

        return SolrResponse::fromSolrResponseArray($response, $this->facetFieldTransformationRegistry);
    }
}
