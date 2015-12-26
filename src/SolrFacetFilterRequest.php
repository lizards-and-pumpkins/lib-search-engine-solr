<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRange;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequestField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequestRangedField;

class SolrFacetFilterRequest
{
    /**
     * @var FacetFiltersToIncludeInResult
     */
    private $facetFiltersToIncludeInResult;

    /**
     * @var string[]
     */
    private $filterSelection;

    /**
     * @var FacetFieldTransformationRegistry
     */
    private $facetFieldTransformationRegistry;

    /**
     * @param FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult
     * @param string[] $filterSelection
     * @param FacetFieldTransformationRegistry $facetFieldTransformationRegistry
     */
    public function __construct(
        FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult,
        array $filterSelection,
        FacetFieldTransformationRegistry $facetFieldTransformationRegistry
    ) {
        $this->facetFiltersToIncludeInResult = $facetFiltersToIncludeInResult;
        $this->filterSelection = $filterSelection;
        $this->facetFieldTransformationRegistry = $facetFieldTransformationRegistry;
    }

    /**
     * @return mixed[]
     */
    public function toArray()
    {
        $fields = $this->facetFiltersToIncludeInResult->getFields();

        if (count($fields) === 0) {
            return [];
        }

        return [
            'facet' => 'on',
            'facet.mincount' => 1,
            'facet.field' => $this->getFacetFields(...$fields),
            'facet.query' => $this->getFacetQueries(...$fields),
            'fq' => $this->getSelectedFacetQueries($this->filterSelection),
        ];
    }

    /**
     * @param FacetFilterRequestField[] $fields
     * @return string[]
     */
    private function getFacetFields(FacetFilterRequestField ...$fields)
    {
        return array_reduce($fields, function (array $carry, FacetFilterRequestField $field) {
            if ($field->isRanged()) {
                return $carry;
            }
            return array_merge($carry, [(string) $field->getAttributeCode()]);
        }, []);
    }

    /**
     * @param FacetFilterRequestField[] $fields
     * @return string[]
     */
    private function getFacetQueries(FacetFilterRequestField ...$fields)
    {
        return array_reduce($fields, function (array $carry, FacetFilterRequestField $field) {
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
            $from = $this->getRangeBoundaryValue($range->from());
            $to = $this->getRangeBoundaryValue($range->to());
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
            $formattedRanges = array_map(function ($filterValue) use ($transformation) {
                $facetFilterRange = $transformation->decode($filterValue);
                return sprintf('[%s TO %s]', $facetFilterRange->from(), $facetFilterRange->to());
            }, $filterValues);
            return sprintf('%s:(%s)', $filterCode, implode(' OR ', $formattedRanges));
        }

        return sprintf('%s:("%s")', $filterCode, implode('" OR "', $filterValues));
    }

    /**
     * @param string|null $boundary
     * @return string
     */
    private function getRangeBoundaryValue($boundary)
    {
        if (null === $boundary) {
            return '*';
        }

        return $boundary;
    }
}
