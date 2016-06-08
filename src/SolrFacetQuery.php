<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

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
     * @return bool
     */
    public function isRanged()
    {
        return $this->isRanged;
    }

    /**
     * @return AttributeCode
     */
    public function getAttributeCode()
    {
        return $this->attributeCode;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }
}
