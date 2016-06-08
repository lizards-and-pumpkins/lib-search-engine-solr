<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\InvalidFacetQueryFormatException;
use LizardsAndPumpkins\Import\Product\AttributeCode;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrFacetQuery
 */
class SolrFacetQueryTest extends \PHPUnit_Framework_TestCase
{
    public function testExceptionIsThrownIfFacetQueryStringFormatIsInvalid()
    {
        $this->expectException(InvalidFacetQueryFormatException::class);
        $invalidFacetQueryString = 'Invalid facet query string';
        SolrFacetQuery::fromStringAndCount($invalidFacetQueryString, 1);
    }

    public function testRangedFacetFilterCanBeCreated()
    {
        $facetQuery = SolrFacetQuery::fromStringAndCount('foo:[bar TO baz]', 1);
        $this->assertTrue($facetQuery->isRanged());
    }

    public function testPlainFacetFilterCanBeCreated()
    {
        $facetQuery = SolrFacetQuery::fromStringAndCount('foo:(bar)', 1);
        $this->assertFalse($facetQuery->isRanged());
    }

    public function testAttributeCodeIsReturned()
    {
        $attributeCodeString = 'foo';
        $value = 'bar';
        $facetQueryString = sprintf('%s:(%s)', $attributeCodeString, $value);

        $facetQuery = SolrFacetQuery::fromStringAndCount($facetQueryString, 1);
        $result = $facetQuery->getAttributeCode();

        $this->assertInstanceOf(AttributeCode::class, $result);
        $this->assertEquals($attributeCodeString, $result);
    }

    public function testValueIsReturned()
    {
        $attributeCodeString = 'foo';
        $value = 'bar';
        $facetQueryString = sprintf('%s:(%s)', $attributeCodeString, $value);

        $facetQuery = SolrFacetQuery::fromStringAndCount($facetQueryString, 1);
        
        $this->assertSame($value, $facetQuery->getValue());
    }

    public function testCountIsReturned()
    {
        $count = 1;
        $facetQuery = SolrFacetQuery::fromStringAndCount('foo:(bar)', $count);

        $this->assertSame($count, $facetQuery->getCount());
    }
}
