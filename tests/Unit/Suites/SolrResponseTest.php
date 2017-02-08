<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\DataPool\SearchEngine\FacetField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformation;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldValue;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\Import\Product\AttributeCode;
use LizardsAndPumpkins\Import\Product\ProductId;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrResponse
 */
class SolrResponseTest extends TestCase
{
    /**
     * @var FacetFieldTransformationRegistry|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stubFacetFieldTransformationRegistry;

    protected function setUp()
    {
        $this->stubFacetFieldTransformationRegistry = $this->createMock(FacetFieldTransformationRegistry::class);
    }

    public function testExceptionIsThrownIfSolrResponseContainsErrorMessage()
    {
        $testErrorMessage = 'Test error message.';
        $responseArray = ['error' => ['msg' => $testErrorMessage]];

        $this->expectException(SolrException::class);
        $this->expectExceptionMessage($testErrorMessage);

        SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);
    }

    public function testZeroIsReturnedIfSolrResponseDoesNotContainTotalNumberOfResultsElement()
    {
        $responseArray = [];
        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);

        $this->assertSame(0, $response->getTotalNumberOfResults());
    }

    public function testTotalNumberOfResultsIsReturned()
    {
        $responseArray = ['response' => ['numFound' => 5]];
        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);

        $this->assertSame(5, $response->getTotalNumberOfResults());
    }

    public function testEmptyArrayIsReturnedIfSolrResponseDoesNotContainDocumentsElement()
    {
        $responseArray = [];
        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);

        $this->assertSame([], $response->getMatchingProductIds());
    }

    public function testMatchingProductIdsAreReturned()
    {
        $responseArray = [
            'response' => [
                'docs' => [
                    [SolrSearchEngine::PRODUCT_ID_FIELD_NAME => 'foo'],
                    [SolrSearchEngine::PRODUCT_ID_FIELD_NAME => 'bar'],
                ]
            ]
        ];
        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);
        $expectedArray = [new ProductId('foo'), new ProductId('bar')];

        $this->assertEquals($expectedArray, $response->getMatchingProductIds());
    }

    public function testEmptyArrayIsReturnedIfNeitherFacetFieldsNorFacetQueriesArePresentInResponseArray()
    {
        $responseArray = [];
        $selectedFilterAttributeCodes = [];

        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);

        $this->assertSame([], $response->getNonSelectedFacetFields($selectedFilterAttributeCodes));
    }

    public function testFacetFiltersAreReturned()
    {
        $attributeCodeString = 'foo';
        $attributeValue = 'bar';
        $attributeValueCount = 2;

        $responseArray = [
            'facet_counts' => [
                'facet_fields' => [$attributeCodeString => [$attributeValue, $attributeValueCount]]
            ]
        ];
        $selectedFilterAttributeCodes = [];

        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);

        $expectedFacetField = new FacetField(
            AttributeCode::fromString($attributeCodeString),
            new FacetFieldValue($attributeValue, $attributeValueCount)
        );

        $this->assertEquals([$expectedFacetField], $response->getNonSelectedFacetFields($selectedFilterAttributeCodes));
    }

    public function testSelectedFiltersAreNotReturnedAlongWithFacetFilters()
    {
        $attributeCodeString = 'foo';
        $attributeValue = 'bar';
        $attributeValueCount = 2;

        $selectedAttributeCodeString = 'baz';
        $selectedAttributeValue = 'qux';
        $selectedAttributeValueCount = 4;

        $responseArray = [
            'facet_counts' => [
                'facet_fields' => [
                    $attributeCodeString => [$attributeValue, $attributeValueCount],
                    $selectedAttributeCodeString => [$selectedAttributeValue, $selectedAttributeValueCount],
                ]
            ]
        ];
        $selectedFilterAttributeCodes = [$selectedAttributeCodeString];

        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);

        $expectedFacetField = new FacetField(
            AttributeCode::fromString($attributeCodeString),
            new FacetFieldValue($attributeValue, $attributeValueCount)
        );

        $this->assertEquals([$expectedFacetField], $response->getNonSelectedFacetFields($selectedFilterAttributeCodes));
    }

    public function testFacetQueriesAreReturned()
    {
        $attributeCodeString = 'price';
        $attributeValueFrom = '*';
        $attributeValueTo = '500';
        $attributeValueCount = 2;

        $query = sprintf('%s:[%s TO %s]', $attributeCodeString, $attributeValueFrom, $attributeValueTo);
        $encodedQueryValue = sprintf('From %s to %s', $attributeValueFrom, $attributeValueTo);

        $responseArray = [
            'facet_counts' => [
                'facet_queries' => [$query => $attributeValueCount]
            ]
        ];
        $selectedFilterAttributeCodes = [];

        $stubFacetFieldTransformation = $this->createMock(FacetFieldTransformation::class);
        $stubFacetFieldTransformation->method('encode')->willReturn($encodedQueryValue);

        $this->stubFacetFieldTransformationRegistry->method('hasTransformationForCode')->with($attributeCodeString)
            ->willReturn(true);
        $this->stubFacetFieldTransformationRegistry->method('getTransformationByCode')->with($attributeCodeString)
            ->willReturn($stubFacetFieldTransformation);

        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);

        $expectedFacetField = new FacetField(
            AttributeCode::fromString($attributeCodeString),
            new FacetFieldValue($encodedQueryValue, $attributeValueCount)
        );

        $this->assertEquals([$expectedFacetField], $response->getNonSelectedFacetFields($selectedFilterAttributeCodes));
    }

    public function testSelectedFiltersAreNotReturnedAlongWithFacetQueries()
    {
        $attributeCode = 'foo';
        $attributeValue = 'bar';
        $attributeValueCount = 2;

        $selectedAttributeCode = 'baz';
        $selectedAttributeValue = 'qux';
        $selectedAttributeValueCount = 4;

        $responseArray = [
            'facet_counts' => [
                'facet_queries' => [
                    sprintf('%s:(%s)', $attributeCode, $attributeValue) => $attributeValueCount,
                    sprintf('%s:(%s)', $selectedAttributeCode, $selectedAttributeValue) => $selectedAttributeValueCount,
                ]
            ]
        ];

        $selectedFilterAttributeCodes = [$selectedAttributeCode];
        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);

        $expectedFacetField = new FacetField(
            AttributeCode::fromString($attributeCode),
            new FacetFieldValue($attributeValue, $attributeValueCount)
        );

        $this->assertEquals([$expectedFacetField], $response->getNonSelectedFacetFields($selectedFilterAttributeCodes));
    }
}
