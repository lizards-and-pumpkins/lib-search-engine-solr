<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformation;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRange;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequestField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequestRangedField;
use LizardsAndPumpkins\Import\Product\AttributeCode;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrFacetFilterRequest
 */
class SolrFacetFilterRequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FacetFiltersToIncludeInResult|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stubFacetFiltersToIncludeInResult;

    /**
     * @var FacetFieldTransformationRegistry|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stubFacetFieldTransformationRegistry;

    /**
     * @param string $attributeCodeString
     * @return FacetFilterRequestField|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createStubFacetFilterRequestField($attributeCodeString)
    {
        $stubAttribute = $this->createMock(AttributeCode::class);
        $stubAttribute->method('__toString')->willReturn($attributeCodeString);

        $stubFacetFilterRequestField = $this->createMock(FacetFilterRequestField::class);
        $stubFacetFilterRequestField->method('getAttributeCode')->willReturn($stubAttribute);

        return $stubFacetFilterRequestField;
    }

    /**
     * @param string $attributeCodeString
     * @param string|null $rangeFrom
     * @param string|null $rangeTo
     * @return FacetFilterRequestRangedField|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createStubFacetFilterRequestRangedField($attributeCodeString, $rangeFrom, $rangeTo)
    {
        $stubAttribute = $this->createMock(AttributeCode::class);
        $stubAttribute->method('__toString')->willReturn($attributeCodeString);

        $stubFacetFilterRange = $this->createMock(FacetFilterRange::class);
        $stubFacetFilterRange->method('from')->willReturn($rangeFrom);
        $stubFacetFilterRange->method('to')->willReturn($rangeTo);

        $stubFacetFilterRequestRangedField = $this->createMock(FacetFilterRequestRangedField::class);
        $stubFacetFilterRequestRangedField->method('getAttributeCode')->willReturn($stubAttribute);
        $stubFacetFilterRequestRangedField->method('isRanged')->willReturn(true);
        $stubFacetFilterRequestRangedField->method('getRanges')->willReturn([$stubFacetFilterRange]);

        return $stubFacetFilterRequestRangedField;
    }

    protected function setUp()
    {
        $this->stubFacetFiltersToIncludeInResult = $this->createMock(FacetFiltersToIncludeInResult::class);
        $this->stubFacetFieldTransformationRegistry = $this->createMock(FacetFieldTransformationRegistry::class);
    }

    public function testEmptyArrayIsReturnedIfNoFacetFieldsAreRequested()
    {
        $this->stubFacetFiltersToIncludeInResult->method('getFields')->willReturn([]);
        $testFilterSelection = [];

        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $this->stubFacetFiltersToIncludeInResult,
            $testFilterSelection,
            $this->stubFacetFieldTransformationRegistry
        );

        $this->assertSame([], $solrFacetFilterRequest->toArray());
    }

    public function testNonRangedFacetFieldsAreAddedToFacetFieldElementOfResultArray()
    {
        $testAttributeCode = 'foo';

        $stubField = $this->createStubFacetFilterRequestField($testAttributeCode);
        $this->stubFacetFiltersToIncludeInResult->method('getFields')->willReturn([$stubField]);

        $testFilterSelection = [];

        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $this->stubFacetFiltersToIncludeInResult,
            $testFilterSelection,
            $this->stubFacetFieldTransformationRegistry
        );

        $expectedArray = [
            'facet' => 'on',
            'facet.mincount' => 1,
            'facet.limit' => -1,
            'facet.sort' => 'index',
            'facet.field' => [$testAttributeCode],
            'facet.query' => [],
            'fq' => [],
        ];

        $this->assertSame($expectedArray, $solrFacetFilterRequest->toArray());
    }

    public function testRangedFacetFieldsAreAddedToFacetQueryElementOfResultArray()
    {
        $testAttributeCode = 'foo';
        $rangeFrom = null;
        $rangeTo = 10;

        $stubRangedField = $this->createStubFacetFilterRequestRangedField($testAttributeCode, $rangeFrom, $rangeTo);
        $this->stubFacetFiltersToIncludeInResult->method('getFields')->willReturn([$stubRangedField]);

        $testFilterSelection = [];

        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $this->stubFacetFiltersToIncludeInResult,
            $testFilterSelection,
            $this->stubFacetFieldTransformationRegistry
        );

        $expectedArray = [
            'facet' => 'on',
            'facet.mincount' => 1,
            'facet.limit' => -1,
            'facet.sort' => 'index',
            'facet.field' => [],
            'facet.query' => [sprintf('%s:[%s TO %s]', $testAttributeCode, '*', 10)],
            'fq' => [],
        ];

        $this->assertSame($expectedArray, $solrFacetFilterRequest->toArray());
    }

    public function testSelectedFieldsAreAddedToFqElementOfResultArray()
    {
        $testAttributeCode = 'foo';

        $stubField = $this->createStubFacetFilterRequestField($testAttributeCode);
        $this->stubFacetFiltersToIncludeInResult->method('getFields')->willReturn([$stubField]);

        $testFilterSelection = ['foo' => ['bar', 'baz']];

        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $this->stubFacetFiltersToIncludeInResult,
            $testFilterSelection,
            $this->stubFacetFieldTransformationRegistry
        );

        $expectedArray = [
            'facet' => 'on',
            'facet.mincount' => 1,
            'facet.limit' => -1,
            'facet.sort' => 'index',
            'facet.field' => ['foo'],
            'facet.query' => [],
            'fq' => ['foo:("bar" OR "baz")'],
        ];

        $this->assertSame($expectedArray, $solrFacetFilterRequest->toArray());
    }

    public function testSpecialCharactersInSelectedFieldsInFqElementOfResultArrayAreEscaped()
    {
        $testAttributeCode = 'foo';

        $stubField = $this->createStubFacetFilterRequestField($testAttributeCode);
        $this->stubFacetFiltersToIncludeInResult->method('getFields')->willReturn([$stubField]);

        $testFilterSelection = ['fo+o' => ['ba\r', 'ba"z']];

        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $this->stubFacetFiltersToIncludeInResult,
            $testFilterSelection,
            $this->stubFacetFieldTransformationRegistry
        );

        $expectedArray = [
            'facet' => 'on',
            'facet.mincount' => 1,
            'facet.limit' => -1,
            'facet.sort' => 'index',
            'facet.field' => ['foo'],
            'facet.query' => [],
            'fq' => ['fo\+o:("ba\\\\r" OR "ba\"z")'],
        ];

        $this->assertSame($expectedArray, $solrFacetFilterRequest->toArray());
    }

    public function testFacetFieldTransformationIsAppliedToFacetField()
    {
        $testRangedAttributeCode = 'foo';
        $testNormalAttributeCode = 'bar';

        $this->stubFacetFiltersToIncludeInResult->method('getFields')->willReturn([
            $this->createStubFacetFilterRequestField($testRangedAttributeCode),
            $this->createStubFacetFilterRequestField($testNormalAttributeCode),
        ]);

        $testFilterSelection = [
            $testRangedAttributeCode => ['Value does not matter as it will be transformed.'],
            $testNormalAttributeCode => ['Does not matter either.'],
        ];

        $rangeFrom = 'baz';
        $rangeTo = 'qux';
        $stubFacetFilterRange = $this->createMock(FacetFilterRange::class);
        $stubFacetFilterRange->method('from')->willReturn($rangeFrom);
        $stubFacetFilterRange->method('to')->willReturn($rangeTo);

        $stubRangedTransformation = $this->createMock(FacetFieldTransformation::class);
        $stubRangedTransformation->method('decode')->willReturn($stubFacetFilterRange);

        $transformedValue = 'Transformed value';
        $stubNormalTransformation = $this->createMock(FacetFieldTransformation::class);
        $stubNormalTransformation->method('decode')->willReturn($transformedValue);

        $this->stubFacetFieldTransformationRegistry->method('hasTransformationForCode')->willReturn(true);
        $this->stubFacetFieldTransformationRegistry->method('getTransformationByCode')->willReturnMap([
            [$testRangedAttributeCode, $stubRangedTransformation],
            [$testNormalAttributeCode, $stubNormalTransformation],
        ]);

        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $this->stubFacetFiltersToIncludeInResult,
            $testFilterSelection,
            $this->stubFacetFieldTransformationRegistry
        );

        $expectedArray = [
            'facet' => 'on',
            'facet.mincount' => 1,
            'facet.limit' => -1,
            'facet.sort' => 'index',
            'facet.field' => [$testRangedAttributeCode, $testNormalAttributeCode],
            'facet.query' => [],
            'fq' => [
                sprintf('%s:([%s TO %s])', $testRangedAttributeCode, $rangeFrom, $rangeTo),
                sprintf('%s:("%s")', $testNormalAttributeCode, $transformedValue),
            ]
        ];

        $this->assertSame($expectedArray, $solrFacetFilterRequest->toArray());
    }
}
