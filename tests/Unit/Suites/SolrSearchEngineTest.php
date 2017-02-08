<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\SelfContainedContext;
use LizardsAndPumpkins\Context\SelfContainedContextBuilder;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldValue;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortBy;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortDirection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionAnything;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionEqual;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngineResponse;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\SolrHttpClient;
use LizardsAndPumpkins\Import\Product\AttributeCode;
use LizardsAndPumpkins\Import\Product\ProductId;
use LizardsAndPumpkins\ProductSearch\QueryOptions;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrSearchEngine
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrDocumentBuilder
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrFacetFilterRequest
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrQuery
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrResponse
 */
class SolrSearchEngineTest extends TestCase
{
    /**
     * @var SolrSearchEngine
     */
    private $searchEngine;

    /**
     * @var SolrHttpClient|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockHttpClient;

    private function createTestContext() : Context
    {
        return SelfContainedContextBuilder::rehydrateContext([
            'website' => 'website',
            'version' => '-1'
        ]);
    }

    /**
     * @param string $sortByFieldCode
     * @param string $sortDirection
     * @return SortBy|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createStubSortOrderConfig(string $sortByFieldCode, string $sortDirection) : SortBy
    {
        $stubAttributeCode = $this->createMock(AttributeCode::class);
        $stubAttributeCode->method('__toString')->willReturn($sortByFieldCode);

        $sortOrderConfig = $this->createMock(SortBy::class);
        $sortOrderConfig->method('getAttributeCode')->willReturn($stubAttributeCode);
        $sortOrderConfig->method('getSelectedDirection')->willReturn(SortDirection::create($sortDirection));

        return $sortOrderConfig;
    }

    /**
     * @param array[] $filterSelection
     * @return QueryOptions|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createStubQueryOptions(array $filterSelection) : QueryOptions
    {
        $facetFiltersToIncludeInResult = new FacetFiltersToIncludeInResult();
        $stubSortOrderConfig = $this->createStubSortOrderConfig('foo', SortDirection::ASC);

        $stubQueryOptions = $this->createMock(QueryOptions::class);
        $stubQueryOptions->method('getFilterSelection')->willReturn($filterSelection);
        $stubQueryOptions->method('getContext')->willReturn($this->createTestContext());
        $stubQueryOptions->method('getFacetFiltersToIncludeInResult')->willReturn($facetFiltersToIncludeInResult);
        $stubQueryOptions->method('getRowsPerPage')->willReturn(100);
        $stubQueryOptions->method('getPageNumber')->willReturn(0);
        $stubQueryOptions->method('getSortBy')->willReturn($stubSortOrderConfig);

        return $stubQueryOptions;
    }

    protected function setUp()
    {
        $this->mockHttpClient = $this->createMock(SolrHttpClient::class);

        $stubTransformationRegistry = $this->createMock(FacetFieldTransformationRegistry::class);

        $this->searchEngine = new SolrSearchEngine($this->mockHttpClient, $stubTransformationRegistry);
    }

    public function testUpdateRequestContainingSolrDocumentsIsSentToHttpClient()
    {
        $searchDocumentFieldCollection = SearchDocumentFieldCollection::fromArray(['foo' => 'bar']);
        $context = new SelfContainedContext(['baz' => 'qux']);
        $productId = new ProductId(uniqid());

        $searchDocument = new SearchDocument($searchDocumentFieldCollection, $context, $productId);
        $expectedSolrDocument = SolrDocumentBuilder::fromSearchDocument($searchDocument);

        $this->mockHttpClient->expects($this->once())->method('update')->with([$expectedSolrDocument]);
        $this->searchEngine->addDocument($searchDocument);
    }

    public function testUpdateRequestFlushingSolrIndexIsSentToHttpClient()
    {
        $this->mockHttpClient->expects($this->once())->method('update')->with(['delete' => ['query' => '*:*']]);
        $this->searchEngine->clear();
    }

    public function testSearchEngineResponseIsReturned()
    {
        $this->mockHttpClient->method('select')->willReturn([]);

        $searchCriteria = new SearchCriterionEqual('foo', 'bar');
        $filterSelection = [];

        $result = $this->searchEngine->query($searchCriteria, $this->createStubQueryOptions($filterSelection));

        $this->assertInstanceOf(SearchEngineResponse::class, $result);
    }

    public function testSearchEngineResponseContainsFacetFields()
    {
        $attributeCode = 'foo';
        $attributeValue = 'bar';
        $attributeValueCount = 1;

        $this->mockHttpClient->method('select')->willReturn([
            'facet_counts' => [
                'facet_fields' => [$attributeCode => [$attributeValue, $attributeValueCount]]
            ]
        ]);

        $searchCriteria = new SearchCriterionAnything();
        $filterSelection = [$attributeCode => [$attributeValue]];

        $response = $this->searchEngine->query($searchCriteria, $this->createStubQueryOptions($filterSelection));

        $expectedFacetFieldCollection = new FacetFieldCollection(
            new FacetField(
                AttributeCode::fromString($attributeCode),
                new FacetFieldValue($attributeValue, $attributeValueCount)
            )
        );

        $this->assertEquals($expectedFacetFieldCollection, $response->getFacetFieldCollection());
    }
}
