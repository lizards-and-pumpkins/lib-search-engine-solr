<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\Catalog\Search\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderConfig;
use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderDirection;
use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\ContextBuilder;
use LizardsAndPumpkins\Context\SelfContainedContext;
use LizardsAndPumpkins\Context\SelfContainedContextBuilder;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetField;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldValue;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\QueryOptions;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionAnything;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionEqual;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngineResponse;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\SolrHttpClient;
use LizardsAndPumpkins\Product\AttributeCode;
use LizardsAndPumpkins\Product\ProductId;
use \PHPUnit_Framework_MockObject_MockObject as MockObject;

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

    private $testGlobalProductListingCriteriaFieldName = 'bar';

    private $testGlobalProductListingCriteriaFieldValue = 'baz';

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

    /**
     * @param array[] $filterSelection
     * @return QueryOptions|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createStubQueryOptions(array $filterSelection)
    {
        $facetFiltersToIncludeInResult = new FacetFiltersToIncludeInResult();
        $stubSortOrderConfig = $this->createStubSortOrderConfig('foo', SortOrderDirection::ASC);

        $stubQueryOptions = $this->getMock(QueryOptions::class, [], [], '', false);
        $stubQueryOptions->method('getFilterSelection')->willReturn($filterSelection);
        $stubQueryOptions->method('getContext')->willReturn($this->createTestContext());
        $stubQueryOptions->method('getFacetFiltersToIncludeInResult')->willReturn($facetFiltersToIncludeInResult);
        $stubQueryOptions->method('getRowsPerPage')->willReturn(100);
        $stubQueryOptions->method('getPageNumber')->willReturn(0);
        $stubQueryOptions->method('getSortOrderConfig')->willReturn($stubSortOrderConfig);

        return $stubQueryOptions;
    }

    protected function setUp()
    {
        $this->mockHttpClient = $this->getMock(SolrHttpClient::class, [], [], '', false);

        /** @var FacetFieldTransformationRegistry|MockObject $stubTransformationRegistry */
        $stubTransformationRegistry = $this->getMock(FacetFieldTransformationRegistry::class);

        $testGlobalProductListingCriteria = SearchCriterionEqual::create(
            $this->testGlobalProductListingCriteriaFieldName,
            $this->testGlobalProductListingCriteriaFieldValue
        );

        $this->searchEngine = new SolrSearchEngine(
            $this->mockHttpClient,
            $testGlobalProductListingCriteria,
            $stubTransformationRegistry
        );
    }

    public function testUpdateRequestContainingSolrDocumentsIsSentToHttpClient()
    {
        $searchDocumentFieldCollection = SearchDocumentFieldCollection::fromArray(['foo' => 'bar']);
        $context = SelfContainedContext::fromArray(['baz' => 'qux']);
        $productId = ProductId::fromString(uniqid());

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

        $searchCriteria = SearchCriterionEqual::create('foo', 'bar');
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

        $searchCriteria = SearchCriterionAnything::create();
        $filterSelection = [$attributeCode => [$attributeValue]];

        $response = $this->searchEngine->query($searchCriteria, $this->createStubQueryOptions($filterSelection));

        $expectedFacetFieldCollection = new FacetFieldCollection(
            new FacetField(
                AttributeCode::fromString($attributeCode),
                FacetFieldValue::create($attributeValue, $attributeValueCount)
            )
        );

        $this->assertEquals($expectedFacetFieldCollection, $response->getFacetFieldCollection());
    }

    public function testSolrQuerySentToHttpClientContainsGlobalProductListingCriteria()
    {
        $searchString = 'foo';
        $filterSelection = [];

        $spy = $this->once();
        $this->mockHttpClient->expects($spy)->method('select')->willReturn([]);

        $this->searchEngine->queryFullText($searchString, $this->createStubQueryOptions($filterSelection));

        $queryString = $spy->getInvocations()[0]->parameters[0]['q'];

        $expectationRegExp = sprintf(
            '/^\(\(\(full_text_search:"%s"\) AND %s:"%s"\)\)/',
            $searchString,
            $this->testGlobalProductListingCriteriaFieldName,
            $this->testGlobalProductListingCriteriaFieldValue
        );

        $this->assertRegExp($expectationRegExp, $queryString);
    }
}
