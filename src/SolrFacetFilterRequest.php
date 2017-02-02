<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
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
     * @var array[]
     */
    private $filterSelection;

    /**
     * @var FacetFieldTransformationRegistry
     */
    private $facetFieldTransformationRegistry;

    /**
     * @param FacetFiltersToIncludeInResult $facetFiltersToIncludeInResult
     * @param array[] $filterSelection
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
    public function toArray() : array
    {
        return array_merge($this->getFacetsArray(), $this->getFacetQueriesArray());
    }

    /**
     * @return mixed[]
     */
    private function getFacetsArray(): array
    {
        $fields = $this->facetFiltersToIncludeInResult->getFields();

        if (count($fields) === 0) {
            return [];
        }

        return [
            'facet' => 'on',
            'facet.mincount' => 1,
            'facet.limit' => -1,
            'facet.sort' => 'index',
            'facet.field' => $this->getFacetFields(...$fields),
            'facet.query' => $this->getFacetQueries(...$fields),
        ];
    }

    /**
     * @return array[]
     */
    private function getFacetQueriesArray(): array
    {
        if (count($this->filterSelection) === 0) {
            return [];
        }

        return ['fq' => $this->getSelectedFacetQueries($this->filterSelection)];
    }

    /**
     * @param FacetFilterRequestField[] $fields
     * @return string[]
     */
    private function getFacetFields(FacetFilterRequestField ...$fields) : array
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
    private function getFacetQueries(FacetFilterRequestField ...$fields) : array
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
    private function getRangedFieldRanges(FacetFilterRequestRangedField $field) : array
    {
        return array_reduce($field->getRanges(), function (array $carry, FacetFilterRange $range) use ($field) {
            $from = $this->getRangeBoundaryValue($range->from());
            $to = $this->getRangeBoundaryValue($range->to());
            return array_merge($carry, [sprintf('%s:[%s TO %s]', $field->getAttributeCode(), $from, $to)]);
        }, []);
    }

    /**
     * @param array[] $filterSelection
     * @return string[]
     */
    private function getSelectedFacetQueries(array $filterSelection) : array
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
    private function getFormattedFacetQueryValues(string $filterCode, array $filterValues) : string
    {
        if ($this->facetFieldTransformationRegistry->hasTransformationForCode($filterCode)) {
            $transformation = $this->facetFieldTransformationRegistry->getTransformationByCode($filterCode);
            $formattedValues = array_map(function (string $filterValue) use ($transformation) {
                $facetValue = $transformation->decode($filterValue);
                
                if ($facetValue instanceof FacetFilterRange) {
                    $from = $this->escapeQueryChars((string) $facetValue->from());
                    $to = $this->escapeQueryChars((string) $facetValue->to());

                    return sprintf('[%s TO %s]', $from, $to);
                }
                
                return sprintf('"%s"', $this->escapeQueryChars($facetValue));
            }, $filterValues);
            return sprintf('%s:(%s)', $this->escapeQueryChars($filterCode), implode(' OR ', $formattedValues));
        }

        $escapedFilterValues = array_map([$this, 'escapeQueryChars'], $filterValues);
        return sprintf('%s:("%s")', $this->escapeQueryChars($filterCode), implode('" OR "', $escapedFilterValues));
    }

    /**
     * @param mixed $boundary
     * @return mixed
     */
    private function getRangeBoundaryValue($boundary)
    {
        if (null === $boundary) {
            return '*';
        }

        return $boundary;
    }

    private function escapeQueryChars(string $queryString) : string
    {
        $src = ['\\', '+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '~', '*', '?', ':', '"', ';', '/'];

        $replace = array_map(function ($string) {
            return '\\' . $string;
        }, $src);

        return str_replace($src, $replace, $queryString);
    }
}
