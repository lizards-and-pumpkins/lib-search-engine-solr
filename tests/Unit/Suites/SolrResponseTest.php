<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\DataPool\SearchEngine\Exception\NoFacetFieldTransformationRegisteredException;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformation;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldValue;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\Import\Product\AttributeCode;
use LizardsAndPumpkins\Import\Product\ProductId;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrResponse
 */
class SolrResponseTest extends \PHPUnit_Framework_TestCase
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
        $expectedArray = [ProductId::fromString('foo'), ProductId::fromString('bar')];

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
            FacetFieldValue::create($attributeValue, $attributeValueCount)
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
            FacetFieldValue::create($attributeValue, $attributeValueCount)
        );

        $this->assertEquals([$expectedFacetField], $response->getNonSelectedFacetFields($selectedFilterAttributeCodes));
    }

    public function testExceptionIsThrownIfNoTransformationIsRegisteredForFacetField()
    {
        $this->expectException(NoFacetFieldTransformationRegisteredException::class);

        $attributeCodeString = 'price';
        $attributeValueFrom = '*';
        $attributeValueTo = '500';
        $attributeValueCount = 2;

        $query = sprintf('%s:[%s TO %s]', $attributeCodeString, $attributeValueFrom, $attributeValueTo);

        $responseArray = [
            'facet_counts' => [
                'facet_queries' => [$query => $attributeValueCount]
            ]
        ];
        $selectedFilterAttributeCodes = [];

        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);
        $response->getNonSelectedFacetFields($selectedFilterAttributeCodes);
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
            FacetFieldValue::create($encodedQueryValue, $attributeValueCount)
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
            FacetFieldValue::create($attributeValue, $attributeValueCount)
        );

        $this->assertEquals([$expectedFacetField], $response->getNonSelectedFacetFields($selectedFilterAttributeCodes));
    }

    public function testFacetFieldTransformationsAreAppliedToFacetQueries()
    {
        $rangedAttributeCode = 'price';
        $rangedAttributeValueFrom = '*';
        $rangedAttributeValueTo = '500';
        $rangedAttributeValueCount = 2;

        $query = sprintf('%s:[%s TO %s]', $rangedAttributeCode, $rangedAttributeValueFrom, $rangedAttributeValueTo);
        $encodedRangedValue = sprintf('From %s to %s', $rangedAttributeValueFrom, $rangedAttributeValueTo);

        $stubRangedFacetFieldTransformation = $this->createMock(FacetFieldTransformation::class);
        $stubRangedFacetFieldTransformation->method('encode')->willReturn($encodedRangedValue);

        $plainAttributeCode = 'foo';
        $plainAttributeValue = 'bar';
        $plainAttributeQuery = sprintf('%s:(%s)', $plainAttributeCode, $plainAttributeValue);
        $plainAttributeValueCount = 4;
        $encodedPlainValue = 'baz';

        $stubPlainFacetFieldTransformation = $this->createMock(FacetFieldTransformation::class);
        $stubPlainFacetFieldTransformation->method('encode')->willReturn($encodedPlainValue);

        $this->stubFacetFieldTransformationRegistry->method('hasTransformationForCode')->willReturn(true);
        $this->stubFacetFieldTransformationRegistry->method('getTransformationByCode')->willReturnMap([
            [$rangedAttributeCode, $stubRangedFacetFieldTransformation],
            [$plainAttributeCode, $stubPlainFacetFieldTransformation],
        ]);

        $responseArray = [
            'facet_counts' => [
                'facet_queries' => [
                    $query => $rangedAttributeValueCount,
                    $plainAttributeQuery => $plainAttributeValueCount
                ]
            ]
        ];

        $selectedFilterAttributeCodes = [];
        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);

        $expectedResult = [
            new FacetField(
                AttributeCode::fromString($rangedAttributeCode),
                FacetFieldValue::create($encodedRangedValue, $rangedAttributeValueCount)
            ),
            new FacetField(
                AttributeCode::fromString($plainAttributeCode),
                FacetFieldValue::create($encodedPlainValue, $plainAttributeValueCount)
            ),
        ];

        $this->assertEquals($expectedResult, $response->getNonSelectedFacetFields($selectedFilterAttributeCodes));
    }

    public function testPlainValueIsNotEncodedIfNoTransformationIsRegistered()
    {
        $attributeCode = 'foo';
        $attributeValue = 'bar';
        $attributeValueCount = 4;

        $this->stubFacetFieldTransformationRegistry->method('hasTransformationForCode')->willReturn(false);

        $responseArray = [
            'facet_counts' => [
                'facet_queries' => [sprintf('%s:(%s)', $attributeCode, $attributeValue) => $attributeValueCount]
            ]
        ];

        $selectedFilterAttributeCodes = [];
        $response = SolrResponse::fromSolrResponseArray($responseArray, $this->stubFacetFieldTransformationRegistry);

        $expectedResult = [
            new FacetField(
                AttributeCode::fromString($attributeCode),
                FacetFieldValue::create($attributeValue, $attributeValueCount)
            )
        ];

        $this->assertEquals($expectedResult, $response->getNonSelectedFacetFields($selectedFilterAttributeCodes));
    }
}
