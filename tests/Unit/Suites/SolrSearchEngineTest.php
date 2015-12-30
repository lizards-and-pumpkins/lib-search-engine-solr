<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderConfig;
use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderDirection;
use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\ContextBuilder;
use LizardsAndPumpkins\Context\SelfContainedContext;
use LizardsAndPumpkins\Context\SelfContainedContextBuilder;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldValue;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionAnything;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionEqual;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngineResponse;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\SolrHttpClient;
use LizardsAndPumpkins\Product\AttributeCode;
use LizardsAndPumpkins\Product\ProductId;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrSearchEngine
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrDocumentBuilder
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrFacetFilterRequest
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorAnything
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrQuery
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrResponse
 */
class SolrSearchEngineTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SolrSearchEngine
     */
    private $searchEngine;

    /**
     * @var SolrHttpClient|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockHttpClient;

    /**
     * @return Context
     */
    private function createTestContext()
    {
        return SelfContainedContextBuilder::rehydrateContext([
            'website' => 'website',
            'version' => '-1'
        ]);
    }

    /**
     * @param string $sortByFieldCode
     * @param string $sortDirection
     * @return SortOrderConfig|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createStubSortOrderConfig($sortByFieldCode, $sortDirection)
    {
        $stubAttributeCode = $this->getMock(AttributeCode::class, [], [], '', false);
        $stubAttributeCode->method('__toString')->willReturn($sortByFieldCode);

        $sortOrderConfig = $this->getMock(SortOrderConfig::class, [], [], '', false);
        $sortOrderConfig->method('getAttributeCode')->willReturn($stubAttributeCode);
        $sortOrderConfig->method('getSelectedDirection')->willReturn($sortDirection);

        return $sortOrderConfig;
    }

    protected function setUp()
    {
        $this->mockHttpClient = $this->getMock(SolrHttpClient::class, [], [], '', false);
        $testSearchableAttributes = ['foo', 'baz'];
        $stubTransformationRegistry = $this->getMock(FacetFieldTransformationRegistry::class);

        $this->searchEngine = new SolrSearchEngine(
            $this->mockHttpClient,
            $testSearchableAttributes,
            $stubTransformationRegistry
        );
    }

    public function testUpdateRequestContainingSolrDocumentsIsSentToHttpClient()
    {
        $searchDocumentFieldCollection = SearchDocumentFieldCollection::fromArray(['foo' => 'bar']);
        $context = SelfContainedContext::fromArray(['baz' => 'qux']);
        $productId = ProductId::fromString(uniqid());

        $searchDocument = new SearchDocument($searchDocumentFieldCollection, $context, $productId);
        $searchDocumentCollection = new SearchDocumentCollection($searchDocument);
        $expectedSolrDocument = SolrDocumentBuilder::fromSearchDocument($searchDocument);

        $this->mockHttpClient->expects($this->once())->method('update')->with([$expectedSolrDocument]);
        $this->searchEngine->addSearchDocumentCollection($searchDocumentCollection);
    }

    public function testUpdateRequestFlushingSolrIndexIsSentToHttpClient()
    {
        $this->mockHttpClient->expects($this->once())->method('update')->with(['delete' => ['query' => '*:*']]);
        $this->searchEngine->clear();
    }

    public function testSearchEngineResponseIsReturned()
    {
        $this->mockHttpClient->method('select')->willReturn([]);

        $searchCriteria = SearchCriterionEqual::create('foo', 'bar');
        $filterSelection = [];
        $context = $this->createTestContext();
        $facetFiltersToIncludeInResult = new FacetFiltersToIncludeInResult();
        $rowsPerPage = 100;
        $pageNumber = 0;
        $sortOrderConfig = $this->createStubSortOrderConfig('foo', SortOrderDirection::ASC);

        $result = $this->searchEngine->query(
            $searchCriteria,
            $filterSelection,
            $context,
            $facetFiltersToIncludeInResult,
            $rowsPerPage,
            $pageNumber,
            $sortOrderConfig
        );

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

        $searchCriteria = SearchCriterionAnything::create();
        $filterSelection = [$attributeCode => [$attributeValue]];
        $context = $this->createTestContext();
        $facetFiltersToIncludeInResult = new FacetFiltersToIncludeInResult();
        $rowsPerPage = 100;
        $pageNumber = 0;
        $sortOrderConfig = $this->createStubSortOrderConfig($attributeCode, SortOrderDirection::ASC);

        $response = $this->searchEngine->query(
            $searchCriteria,
            $filterSelection,
            $context,
            $facetFiltersToIncludeInResult,
            $rowsPerPage,
            $pageNumber,
            $sortOrderConfig
        );

        $expectedFacetFieldCollection = new FacetFieldCollection(
            new FacetField(
                AttributeCode::fromString($attributeCode),
                FacetFieldValue::create($attributeValue, $attributeValueCount)
            )
        );

        $this->assertEquals($expectedFacetFieldCollection, $response->getFacetFieldCollection());
    }
}
