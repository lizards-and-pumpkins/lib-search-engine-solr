<?php

declare(strict_types=1);

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

    private function __construct(AttributeCode $attributeCode, string $value, int $count, bool $isRanged)
    {
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
    public static function fromStringAndCount(string $facetQueryString, int $count)
    {
        if (preg_match('/^(?<attributeCode>[^:]+):\[(?<value>.* TO .*)\]$/', $facetQueryString, $matches)) {
            return new self(AttributeCode::fromString($matches['attributeCode']), $matches['value'], $count, true);
        }

        if (preg_match('/^(?<attributeCode>[^:]+):\((?<value>.*)\)$/', $facetQueryString, $matches)) {
            return new self(AttributeCode::fromString($matches['attributeCode']), $matches['value'], $count, false);
        }

        throw new InvalidFacetQueryFormatException(sprintf('Facet query "%s" format is invalid', $facetQueryString));
    }

    public function getAttributeCode() : AttributeCode
    {
        return $this->attributeCode;
    }

    public function getCount() : int
    {
        return $this->count;
    }

    public function getEncodedValue(FacetFieldTransformationRegistry $transformationRegistry) : string
    {
        if ($this->isRanged) {
            return $this->encodeFilterRange($transformationRegistry);
        }

        return $this->encodeFilter($transformationRegistry);
    }

    private function encodeFilter(FacetFieldTransformationRegistry $transformationRegistry) : string
    {
        if (! $transformationRegistry->hasTransformationForCode((string) $this->attributeCode)) {
            return $this->value;
        }

        $transformation = $transformationRegistry->getTransformationByCode((string) $this->attributeCode);

        return $transformation->encode($this->value);
    }

    private function encodeFilterRange(FacetFieldTransformationRegistry $transformationRegistry) : string
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

    private function getRangeBoundaryValue(string $boundary) : string
    {
        if ('*' === $boundary) {
            return '';
        }

        return $boundary;
    }
}
