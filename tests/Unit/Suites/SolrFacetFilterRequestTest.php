<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformation;
use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRange;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequest;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequestField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequestRangedField;
use LizardsAndPumpkins\Product\AttributeCode;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrFacetFilterRequest
 */
class SolrFacetFilterRequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FacetFilterRequest|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stubFacetFilterRequest;

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
        $stubAttribute = $this->getMock(AttributeCode::class, [], [], '', false);
        $stubAttribute->method('__toString')->willReturn($attributeCodeString);

        $stubFacetFilterRequestField = $this->getMock(FacetFilterRequestField::class, [], [], '', false);
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
        $stubAttribute = $this->getMock(AttributeCode::class, [], [], '', false);
        $stubAttribute->method('__toString')->willReturn($attributeCodeString);

        $stubFacetFilterRange = $this->getMock(FacetFilterRange::class, [], [], '', false);
        $stubFacetFilterRange->method('from')->willReturn($rangeFrom);
        $stubFacetFilterRange->method('to')->willReturn($rangeTo);

        $stubFacetFilterRequestRangedField = $this->getMock(FacetFilterRequestRangedField::class, [], [], '', false);
        $stubFacetFilterRequestRangedField->method('getAttributeCode')->willReturn($stubAttribute);
        $stubFacetFilterRequestRangedField->method('isRanged')->willReturn(true);
        $stubFacetFilterRequestRangedField->method('getRanges')->willReturn([$stubFacetFilterRange]);

        return $stubFacetFilterRequestRangedField;
    }

    protected function setUp()
    {
        $this->stubFacetFilterRequest = $this->getMock(FacetFilterRequest::class, [], [], '', false);
        $this->stubFacetFieldTransformationRegistry = $this->getMock(FacetFieldTransformationRegistry::class);
    }

    public function testEmptyArrayIsReturnedIfNoFacetFieldsAreRequested()
    {
        $this->stubFacetFilterRequest->method('getFields')->willReturn([]);
        $testFilterSelection = [];

        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $this->stubFacetFilterRequest,
            $testFilterSelection,
            $this->stubFacetFieldTransformationRegistry
        );

        $this->assertSame([], $solrFacetFilterRequest->toArray());
    }

    public function testNonRangedFacetFieldsAreAddedToFacetFieldElementOfResultArray()
    {
        $testAttributeCode = 'foo';

        $stubField = $this->createStubFacetFilterRequestField($testAttributeCode);
        $this->stubFacetFilterRequest->method('getFields')->willReturn([$stubField]);

        $testFilterSelection = [];

        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $this->stubFacetFilterRequest,
            $testFilterSelection,
            $this->stubFacetFieldTransformationRegistry
        );

        $expectedArray = [
            'facet' => 'on',
            'facet.mincount' => 1,
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
        $this->stubFacetFilterRequest->method('getFields')->willReturn([$stubRangedField]);

        $testFilterSelection = [];

        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $this->stubFacetFilterRequest,
            $testFilterSelection,
            $this->stubFacetFieldTransformationRegistry
        );

        $expectedArray = [
            'facet' => 'on',
            'facet.mincount' => 1,
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
        $this->stubFacetFilterRequest->method('getFields')->willReturn([$stubField]);

        $testFilterSelection = ['foo' => ['bar', 'baz']];

        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $this->stubFacetFilterRequest,
            $testFilterSelection,
            $this->stubFacetFieldTransformationRegistry
        );

        $expectedArray = [
            'facet' => 'on',
            'facet.mincount' => 1,
            'facet.field' => ['foo'],
            'facet.query' => [],
            'fq' => ['foo:("bar" OR "baz")'],
        ];

        $this->assertSame($expectedArray, $solrFacetFilterRequest->toArray());
    }

    public function testFacetFieldTransformationIsAppliedToFacetField()
    {
        $testAttributeCode = 'foo';

        $stubField = $this->createStubFacetFilterRequestField($testAttributeCode);
        $this->stubFacetFilterRequest->method('getFields')->willReturn([$stubField]);

        $testFilterSelection = ['foo' => ['Value does not really matter as it will be transformed in any way.']];

        $stubFacetFilterRange = $this->getMock(FacetFilterRange::class, [], [], '', false);
        $stubFacetFilterRange->method('from')->willReturn('bar');
        $stubFacetFilterRange->method('to')->willReturn('baz');

        $stubTransformation = $this->getMock(FacetFieldTransformation::class, [], [], '', false);
        $stubTransformation->method('decode')->willReturn($stubFacetFilterRange);

        $this->stubFacetFieldTransformationRegistry->method('hasTransformationForCode')->willReturn(true);
        $this->stubFacetFieldTransformationRegistry->method('getTransformationByCode')->willReturn($stubTransformation);

        $solrFacetFilterRequest = new SolrFacetFilterRequest(
            $this->stubFacetFilterRequest,
            $testFilterSelection,
            $this->stubFacetFieldTransformationRegistry
        );

        $expectedArray = [
            'facet' => 'on',
            'facet.mincount' => 1,
            'facet.field' => ['foo'],
            'facet.query' => [],
            'fq' => ['foo:([bar TO baz])'],
        ];

        $this->assertSame($expectedArray, $solrFacetFilterRequest->toArray());
    }
}
