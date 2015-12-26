<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformation;
use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\Exception\NoFacetFieldTransformationRegisteredException;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldValue;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\Product\AttributeCode;
use LizardsAndPumpkins\Product\ProductId;

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
        $this->stubFacetFieldTransformationRegistry = $this->getMock(FacetFieldTransformationRegistry::class);
    }

    public function testExceptionIsThrownIfSolrResponseContainsErrorMessage()
    {
        $testErrorMessage = 'Test error message.';
        $responseArray = ['error' => ['msg' => $testErrorMessage]];

        $this->setExpectedException(SolrException::class, $testErrorMessage);

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
        $this->setExpectedException(NoFacetFieldTransformationRegisteredException::class);

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

        $stubFacetFieldTransformation = $this->getMock(FacetFieldTransformation::class);
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
        $attributeCodeString = 'price';
        $attributeValueFrom = '*';
        $attributeValueTo = '500';
        $attributeValueCount = 2;

        $query = sprintf('%s:[%s TO %s]', $attributeCodeString, $attributeValueFrom, $attributeValueTo);
        $encodedQueryValue = sprintf('From %s to %s', $attributeValueFrom, $attributeValueTo);

        $selectedAttributeCode = 'length';
        $selectedAttributeQuery = sprintf('%s:[10 TO 20]', $selectedAttributeCode);
        $selectedAttributeValueCount = 4;

        $responseArray = [
            'facet_counts' => [
                'facet_queries' => [
                    $query => $attributeValueCount,
                    $selectedAttributeQuery => $selectedAttributeValueCount
                ]
            ]
        ];

        $selectedFilterAttributeCodes = [$selectedAttributeCode];

        $stubFacetFieldTransformation = $this->getMock(FacetFieldTransformation::class);
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
}
