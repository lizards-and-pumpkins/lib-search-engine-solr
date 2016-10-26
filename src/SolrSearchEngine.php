<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\DataPool\SearchEngine\FacetField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\CompositeSearchCriterion;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriteria;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionLike;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngine;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngineResponse;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\SolrHttpClient;
use LizardsAndPumpkins\ProductSearch\QueryOptions;
use LizardsAndPumpkins\Util\Storage\Clearable;

class SolrSearchEngine implements SearchEngine, Clearable
{
    const DOCUMENT_ID_FIELD_NAME = 'id';
    const PRODUCT_ID_FIELD_NAME = 'product_id';
    const FULL_TEXT_SEARCH_FIELD_NAME = 'full_text_search';

    /**
     * @var SolrHttpClient
     */
    private $client;

    /**
     * @var SearchCriteria
     */
    private $globalProductListingCriteria;

    /**
     * @var FacetFieldTransformationRegistry
     */
    private $facetFieldTransformationRegistry;

    public function __construct(
        SolrHttpClient $client,
        SearchCriteria $globalProductListingCriteria,
        FacetFieldTransformationRegistry $facetFieldTransformationRegistry
    ) {
        $this->client = $client;
        $this->globalProductListingCriteria = $globalProductListingCriteria;
        $this->facetFieldTransformationRegistry = $facetFieldTransformationRegistry;
    }

    public function addDocument(SearchDocument $document)
    {
        $solrDocument = SolrDocumentBuilder::fromSearchDocument($document);
        $this->client->update([$solrDocument]);
    }

    public function query(SearchCriteria $criteria, QueryOptions $queryOptions) : SearchEngineResponse
    {
        $query =  new SolrQuery($criteria, $queryOptions);

        $facetFiltersToIncludeInResult = $queryOptions->getFacetFiltersToIncludeInResult();
        $filterSelection = $queryOptions->getFilterSelection();

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

    public function queryFullText(string $searchString, QueryOptions $queryOptions) : SearchEngineResponse
    {
        $criteria = CompositeSearchCriterion::createAnd(
            new SearchCriterionLike(self::FULL_TEXT_SEARCH_FIELD_NAME, $searchString),
            $this->globalProductListingCriteria
        );

        return $this->query($criteria, $queryOptions);
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
    ) : FacetFieldCollection {
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
    ) : array {
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

    private function querySolr(SolrQuery $query, SolrFacetFilterRequest $facetFilterRequest) : SolrResponse
    {
        $queryParameters = $query->toArray();
        $facetParameters = $facetFilterRequest->toArray();

        $response = $this->client->select(array_merge($queryParameters, $facetParameters));

        return SolrResponse::fromSolrResponseArray($response, $this->facetFieldTransformationRegistry);
    }
}
