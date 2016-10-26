<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\DataPool\SearchEngine\Exception\NoFacetFieldTransformationRegisteredException;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformation;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRange;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\InvalidFacetQueryFormatException;
use LizardsAndPumpkins\Import\Product\AttributeCode;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrFacetQuery
 */
class SolrFacetQueryTest extends \PHPUnit_Framework_TestCase
{
    public function testExceptionIsThrownIfFacetQueryStringIsNotAString()
    {
        $this->expectException(\TypeError::class);
        SolrFacetQuery::fromStringAndCount([], 1);
    }

    public function testExceptionIsThrownIfFacetQueryCountIsNotAnInteger()
    {
        $this->expectException(\TypeError::class);
        SolrFacetQuery::fromStringAndCount('foo:(bar)', false);
    }

    public function testExceptionIsThrownIfFacetQueryStringFormatIsInvalid()
    {
        $this->expectException(InvalidFacetQueryFormatException::class);
        $invalidFacetQueryString = 'Invalid facet query string';
        SolrFacetQuery::fromStringAndCount($invalidFacetQueryString, 1);
    }

    /**
     * @dataProvider facetQueryStringProvider
     */
    public function testFacetQueryIsCreated(string $facetQueryString)
    {
        $this->assertInstanceOf(SolrFacetQuery::class, SolrFacetQuery::fromStringAndCount($facetQueryString, 1));
    }

    /**
     * @return array[]
     */
    public function facetQueryStringProvider() : array
    {
        return [
            ['foo:(bar)'],
            ['foo:[bar TO baz]'],
        ];
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

    public function testCountIsReturned()
    {
        $count = 1;
        $facetQuery = SolrFacetQuery::fromStringAndCount('foo:(bar)', $count);

        $this->assertSame($count, $facetQuery->getCount());
    }

    public function testExceptionIsThrownIfNoTransformationIsRegisteredForRangedFacetField()
    {
        $this->expectException(NoFacetFieldTransformationRegisteredException::class);

        /** @var FacetFieldTransformationRegistry|\PHPUnit_Framework_MockObject_MockObject $stubRegistry */
        $stubRegistry = $this->createMock(FacetFieldTransformationRegistry::class);
        $stubRegistry->method('hasTransformationForCode')->willReturn(false);

        $facetQuery = SolrFacetQuery::fromStringAndCount('foo:[bar TO baz]', 1);
        $facetQuery->getEncodedValue($stubRegistry);
    }

    public function testTransformationIsAppliedToRangedFacetFieldValue()
    {
        $attributeCodeString = 'foo';
        $rangeFrom = '*';
        $rangeTo = 1000;
        $facetQueryString = sprintf('%s:[%s TO %s]', $attributeCodeString, $rangeFrom, $rangeTo);

        $format = 'From %s until %s';

        /** @var FacetFieldTransformation|\PHPUnit_Framework_MockObject_MockObject $stubTransformation */
        $stubTransformation = $this->createMock(FacetFieldTransformation::class);
        $stubTransformation->method('encode')->willReturnCallback(function (FacetFilterRange $range) use ($format) {
            return sprintf($format, $range->from(), $range->to());
        });

        /** @var FacetFieldTransformationRegistry|\PHPUnit_Framework_MockObject_MockObject $stubRegistry */
        $stubRegistry = $this->createMock(FacetFieldTransformationRegistry::class);
        $stubRegistry->method('hasTransformationForCode')->with($attributeCodeString)->willReturn(true);
        $stubRegistry->method('getTransformationByCode')->with($attributeCodeString)->willReturn($stubTransformation);

        $facetQuery = SolrFacetQuery::fromStringAndCount($facetQueryString, 1);

        $expectation = sprintf($format, preg_replace('/^\*$/', '', $rangeFrom), preg_replace('/^\*$/', '', $rangeTo));
        $result = $facetQuery->getEncodedValue($stubRegistry);

        $this->assertSame($expectation, $result);
    }

    public function testUnencodedPlainFacetFieldValueIsReturnedIfNoTransformationIsRegistered()
    {
        $attributeCodeString = 'foo';
        $value = 'bar';
        $facetQueryString = sprintf('%s:(%s)', $attributeCodeString, $value);

        /** @var FacetFieldTransformationRegistry|\PHPUnit_Framework_MockObject_MockObject $stubRegistry */
        $stubRegistry = $this->createMock(FacetFieldTransformationRegistry::class);
        $stubRegistry->method('hasTransformationForCode')->with($attributeCodeString)->willReturn(false);

        $facetQuery = SolrFacetQuery::fromStringAndCount($facetQueryString, 1);

        $this->assertSame($value, $facetQuery->getEncodedValue($stubRegistry));
    }

    public function testTransformationIsAppliedToPlainFacetFieldValue()
    {
        $attributeCodeString = 'foo';
        $value = 'bar';
        $encodedValue = 'baz';
        $facetQueryString = sprintf('%s:(%s)', $attributeCodeString, $value);

        /** @var FacetFieldTransformation|\PHPUnit_Framework_MockObject_MockObject $stubTransformation */
        $stubTransformation = $this->createMock(FacetFieldTransformation::class);
        $stubTransformation->method('encode')->with($value)->willReturn($encodedValue);

        /** @var FacetFieldTransformationRegistry|\PHPUnit_Framework_MockObject_MockObject $stubRegistry */
        $stubRegistry = $this->createMock(FacetFieldTransformationRegistry::class);
        $stubRegistry->method('hasTransformationForCode')->with($attributeCodeString)->willReturn(true);
        $stubRegistry->method('getTransformationByCode')->with($attributeCodeString)->willReturn($stubTransformation);

        $facetQuery = SolrFacetQuery::fromStringAndCount($facetQueryString, 1);

        $this->assertSame($encodedValue, $facetQuery->getEncodedValue($stubRegistry));
    }
}
