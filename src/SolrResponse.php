<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\DataPool\SearchEngine\Exception\NoFacetFieldTransformationRegisteredException;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldValue;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRange;
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
     * @var SolrFacetQuery[]
     */
    private $memoizedFacetQueries;

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
    ) {
        if (isset($rawResponse['error'])) {
            throw new SolrException($rawResponse['error']['msg']);
        }

        return new self($rawResponse, $facetFieldTransformationRegistry);
    }

    /**
     * @return int
     */
    public function getTotalNumberOfResults()
    {
        if (! isset($this->response['response']['numFound'])) {
            return 0;
        }

        return $this->response['response']['numFound'];
    }

    /**
     * @return ProductId[]
     */
    public function getMatchingProductIds()
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
    public function getNonSelectedFacetFields(array $selectedFilterAttributeCodes)
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
    private function getNonSelectedFacetFieldsFromSolrFacetFields(array $selectedFilterAttributeCodes)
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
    private function getNonSelectedFacetFieldsFromSolrFacetQueries(array $selectedFilterAttributeCodes)
    {
        $rawFacetQueries = $this->buildRawFacetQueriesForUnselectedAttributes($selectedFilterAttributeCodes);

        return $this->buildFacetFieldValuesFromRawFacetQueries($rawFacetQueries);
    }

    /**
     * @return array[]
     */
    private function getFacetFields()
    {
        if (! isset($this->response['facet_counts']['facet_fields'])) {
            return [];
        }

        return $this->response['facet_counts']['facet_fields'];
    }

    /**
     * @return SolrFacetQuery[]
     */
    private function getFacetQueries()
    {
        if (null === $this->memoizedFacetQueries) {
            $this->memoizedFacetQueries = $this->extractFacetQueriesFromSolrResponse();
        }

        return $this->memoizedFacetQueries;
    }

    /**
     * @return SolrFacetQuery
     */
    private function extractFacetQueriesFromSolrResponse()
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
    private function getProductIdsOfMatchingDocuments(array $responseDocuments)
    {
        return array_map(function (array $document) {
            return ProductId::fromString($document[SolrSearchEngine::PRODUCT_ID_FIELD_NAME]);
        }, $responseDocuments);
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
     * @param string[] $selectedAttributeCodes
     * @return array[]
     */
    private function buildRawFacetQueriesForUnselectedAttributes(array $selectedAttributeCodes)
    {
        $queries = $this->getFacetQueries();

        return array_reduce($queries, function (array $carry, SolrFacetQuery $query) use ($selectedAttributeCodes) {
            $attributeCode = (string) $query->getAttributeCode();

            if (in_array($attributeCode, $selectedAttributeCodes)) {
                return $carry;
            }

            $carry[$attributeCode][$this->encodeValue($query)] = $query->getCount();

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
     * @param SolrFacetQuery $query
     * @return string
     */
    private function encodeValue(SolrFacetQuery $query)
    {
        if ($query->isRanged()) {
            return $this->encodeFilterRange($query->getAttributeCode(), $query->getValue());
        }

        return $this->encodeFilter($query->getAttributeCode(), $query->getValue());
    }

    /**
     * @param AttributeCode $attributeCode
     * @param string $value
     * @return string
     */
    private function encodeFilter(AttributeCode $attributeCode, $value)
    {
        if (! $this->facetFieldTransformationRegistry->hasTransformationForCode((string) $attributeCode)) {
            return $value;
        }

        $transformation = $this->facetFieldTransformationRegistry->getTransformationByCode((string) $attributeCode);
        
        return $transformation->encode($value);
    }

    /**
     * @param AttributeCode $attributeCode
     * @param string $value
     * @return string
     */
    private function encodeFilterRange(AttributeCode $attributeCode, $value)
    {
        if (! $this->facetFieldTransformationRegistry->hasTransformationForCode((string) $attributeCode)) {
            throw new NoFacetFieldTransformationRegisteredException(
                sprintf('No facet field transformation is registered for "%s" attribute.', $attributeCode)
            );
        }

        $transformation = $this->facetFieldTransformationRegistry->getTransformationByCode((string) $attributeCode);
        $boundaries = explode(' TO ', $value);

        $facetFilterRange = FacetFilterRange::create(
            $this->getRangeBoundaryValue($boundaries[0]),
            $this->getRangeBoundaryValue($boundaries[1])
        );

        return $transformation->encode($facetFilterRange);
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
