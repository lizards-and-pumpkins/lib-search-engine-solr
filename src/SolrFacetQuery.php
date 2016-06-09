<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\DataPool\SearchEngine\Exception\NoFacetFieldTransformationRegisteredException;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRange;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\InvalidFacetQueryFormatException;
use LizardsAndPumpkins\Import\Product\AttributeCode;

class SolrFacetQuery
{
    /**
     * @var AttributeCode
     */
    private $attributeCode;

    /**
     * @var string
     */
    private $value;

    /**
     * @var int
     */
    private $count;

    /**
     * @var bool
     */
    private $isRanged;

    /**
     * @param AttributeCode $attributeCode
     * @param string $value
     * @param int $count
     * @param bool $isRanged
     */
    private function __construct(AttributeCode $attributeCode, $value, $count, $isRanged)
    {
        // TODO: Once PHP 7 is here add signature types

        $this->attributeCode = $attributeCode;
        $this->value = $value;
        $this->count = $count;
        $this->isRanged = $isRanged;
    }

    /**
     * @param string $facetQueryString
     * @param int $count
     * @return SolrFacetQuery
     */
    public static function fromStringAndCount($facetQueryString, $count)
    {
        // TODO: Once PHP 7 is here add signature types

        if (preg_match('/^(?<attributeCode>[^:]+):\[(?<value>.* TO .*)\]$/', $facetQueryString, $matches)) {
            return new self(AttributeCode::fromString($matches['attributeCode']), $matches['value'], $count, true);
        }

        if (preg_match('/^(?<attributeCode>[^:]+):\((?<value>.*)\)$/', $facetQueryString, $matches)) {
            return new self(AttributeCode::fromString($matches['attributeCode']), $matches['value'], $count, false);
        }

        throw new InvalidFacetQueryFormatException(sprintf('Facet query "%s" format is invalid', $facetQueryString));
    }

    /**
     * @return AttributeCode
     */
    public function getAttributeCode()
    {
        return $this->attributeCode;
    }

    /**
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * @param FacetFieldTransformationRegistry $transformationRegistry
     * @return string
     */
    public function getEncodedValue(FacetFieldTransformationRegistry $transformationRegistry)
    {
        if ($this->isRanged) {
            return $this->encodeFilterRange($transformationRegistry);
        }

        return $this->encodeFilter($transformationRegistry);
    }

    /**
     * @param FacetFieldTransformationRegistry $transformationRegistry
     * @return string
     */
    private function encodeFilter(FacetFieldTransformationRegistry $transformationRegistry)
    {
        if (! $transformationRegistry->hasTransformationForCode((string) $this->attributeCode)) {
            return $this->value;
        }

        $transformation = $transformationRegistry->getTransformationByCode((string) $this->attributeCode);

        return $transformation->encode($this->value);
    }

    /**
     * @param FacetFieldTransformationRegistry $transformationRegistry
     * @return string
     */
    private function encodeFilterRange(FacetFieldTransformationRegistry $transformationRegistry)
    {
        if (! $transformationRegistry->hasTransformationForCode((string) $this->attributeCode)) {
            throw new NoFacetFieldTransformationRegisteredException(
                sprintf('No facet field transformation is registered for "%s" attribute.', $this->attributeCode)
            );
        }

        $transformation = $transformationRegistry->getTransformationByCode((string) $this->attributeCode);
        $boundaries = explode(' TO ', $this->value);

        $facetFilterRange = FacetFilterRange::create(
            $this->getRangeBoundaryValue($boundaries[0]),
            $this->getRangeBoundaryValue($boundaries[1])
        );

        return $transformation->encode($facetFilterRange);
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
