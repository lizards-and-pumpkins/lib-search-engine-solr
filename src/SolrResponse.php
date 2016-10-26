<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\DataPool\SearchEngine\FacetField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldValue;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\Import\Product\AttributeCode;
use LizardsAndPumpkins\Import\Product\ProductId;

class SolrResponse
{
    /**
     * @var array[]
     */
    private $response;

    /**
     * @var FacetFieldTransformationRegistry
     */
    private $facetFieldTransformationRegistry;

    /**
     * @param array[] $response
     * @param FacetFieldTransformationRegistry $facetFieldTransformationRegistry
     */
    private function __construct(array $response, FacetFieldTransformationRegistry $facetFieldTransformationRegistry)
    {
        $this->response = $response;
        $this->facetFieldTransformationRegistry = $facetFieldTransformationRegistry;
    }

    /**
     * @param array[] $rawResponse
     * @param FacetFieldTransformationRegistry $facetFieldTransformationRegistry
     * @return SolrResponse
     */
    public static function fromSolrResponseArray(
        array $rawResponse,
        FacetFieldTransformationRegistry $facetFieldTransformationRegistry
    ) : SolrResponse {
        if (isset($rawResponse['error'])) {
            throw new SolrException($rawResponse['error']['msg']);
        }

        return new self($rawResponse, $facetFieldTransformationRegistry);
    }

    public function getTotalNumberOfResults() : int
    {
        if (! isset($this->response['response']['numFound'])) {
            return 0;
        }

        return $this->response['response']['numFound'];
    }

    /**
     * @return ProductId[]
     */
    public function getMatchingProductIds() : array
    {
        if (! isset($this->response['response']) || ! isset($this->response['response']['docs'])) {
            return [];
        }

        return $this->getProductIdsOfMatchingDocuments($this->response['response']['docs']);
    }

    /**
     * @param string[] $selectedFilterAttributeCodes
     * @return FacetField[]
     */
    public function getNonSelectedFacetFields(array $selectedFilterAttributeCodes) : array
    {
        return array_merge(
            $this->getNonSelectedFacetFieldsFromSolrFacetFields($selectedFilterAttributeCodes),
            $this->getNonSelectedFacetFieldsFromSolrFacetQueries($selectedFilterAttributeCodes)
        );
    }

    /**
     * @param string[] $selectedFilterAttributeCodes
     * @return FacetField[]
     */
    private function getNonSelectedFacetFieldsFromSolrFacetFields(array $selectedFilterAttributeCodes) : array
    {
        $facetFieldsArray = $this->getFacetFields();
        $unselectedAttributeCodes = array_diff(array_keys($facetFieldsArray), $selectedFilterAttributeCodes);

        return array_map(function ($attributeCodeString) use ($facetFieldsArray) {
            return $this->createFacetField($attributeCodeString, $facetFieldsArray[$attributeCodeString]);
        }, $unselectedAttributeCodes);
    }

    /**
     * @param string[] $selectedFilterAttributeCodes
     * @return FacetField[]
     */
    private function getNonSelectedFacetFieldsFromSolrFacetQueries(array $selectedFilterAttributeCodes) : array
    {
        $rawFacetQueries = $this->buildRawFacetQueriesForUnselectedAttributes($selectedFilterAttributeCodes);

        return $this->buildFacetFieldValuesFromRawFacetQueries($rawFacetQueries);
    }

    /**
     * @return array[]
     */
    private function getFacetFields() : array
    {
        if (! isset($this->response['facet_counts']['facet_fields'])) {
            return [];
        }

        return $this->response['facet_counts']['facet_fields'];
    }

    /**
     * @return SolrFacetQuery[]
     */
    private function extractFacetQueriesFromSolrResponse() : array
    {
        if (!isset($this->response['facet_counts']['facet_queries'])) {
            return [];
        }

        $rawFacetQueries = $this->response['facet_counts']['facet_queries'];

        return array_map(function($query) use ($rawFacetQueries) {
            return SolrFacetQuery::fromStringAndCount($query, $rawFacetQueries[$query]);
        }, array_keys($rawFacetQueries));
    }

    /**
     * @param mixed[] $responseDocuments
     * @return ProductId[]
     */
    private function getProductIdsOfMatchingDocuments(array $responseDocuments) : array
    {
        return array_map(function (array $document) {
            return new ProductId($document[SolrSearchEngine::PRODUCT_ID_FIELD_NAME]);
        }, $responseDocuments);
    }

    /**
     * @param string $attributeCodeString
     * @param mixed[] $facetFieldsValues
     * @return FacetField
     */
    private function createFacetField(string $attributeCodeString, array $facetFieldsValues) : FacetField
    {
        $attributeCode = AttributeCode::fromString($attributeCodeString);
        $facetFieldValues = array_map(function (array $fieldData) {
            return new FacetFieldValue($fieldData[0], $fieldData[1]);
        }, array_chunk($facetFieldsValues, 2));

        return new FacetField($attributeCode, ...$facetFieldValues);
    }

    /**
     * @param string[] $selectedAttributeCodes
     * @return array[]
     */
    private function buildRawFacetQueriesForUnselectedAttributes(array $selectedAttributeCodes) : array
    {
        $queries = $this->extractFacetQueriesFromSolrResponse();

        return array_reduce($queries, function (array $carry, SolrFacetQuery $query) use ($selectedAttributeCodes) {
            $attributeCode = (string) $query->getAttributeCode();

            if (in_array($attributeCode, $selectedAttributeCodes)) {
                return $carry;
            }

            $value = $query->getEncodedValue($this->facetFieldTransformationRegistry);
            $carry[$attributeCode][$value] = $query->getCount();

            return $carry;
        }, []);
    }

    /**
     * @param array[] $rawFacetQueries
     * @return FacetField[]
     */
    private function buildFacetFieldValuesFromRawFacetQueries(array $rawFacetQueries) : array
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
    private function createFacetFieldValues(array $attributeValueCounts) : array
    {
        return array_map(function ($value) use ($attributeValueCounts) {
            $count = $attributeValueCounts[$value];
            return new FacetFieldValue((string) $value, $count);
        }, array_keys($attributeValueCounts));
    }
}
