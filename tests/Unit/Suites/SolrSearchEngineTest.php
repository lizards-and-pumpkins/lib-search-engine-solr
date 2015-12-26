<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderConfig;
use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\ContextBuilder;
use LizardsAndPumpkins\Context\SelfContainedContextBuilder;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\SolrHttpClient;
use LizardsAndPumpkins\Product\AttributeCode;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrSearchEngine
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
    private $stubSolrHttpClient;

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
        $this->stubSolrHttpClient = $this->getMock(SolrHttpClient::class, [], [], '', false);
        $testSearchableAttributes = ['foo', 'baz'];
        $stubTransformationRegistry = $this->getMock(FacetFieldTransformationRegistry::class);

        $this->searchEngine = new SolrSearchEngine(
            $this->stubSolrHttpClient,
            $testSearchableAttributes,
            $stubTransformationRegistry
        );
    }

    public function testExceptionIsThrownIfSolrResponseContainsErrorMessage()
    {
        $this->markTestSkipped('Already moved to SolrResponse and left here as a reference.');
        $testErrorMessage = 'Test error message.';
        $this->stubSolrHttpClient->method('select')->willReturn(['error' => ['msg' => $testErrorMessage]]);

        $this->setExpectedException(SolrException::class, $testErrorMessage);

        $fieldCode = 'foo';
        $fieldValue = 'bar';

        $searchCriteria = SearchCriterionEqual::create($fieldCode, $fieldValue);
        $filterSelection = [];
        $context = $this->createTestContext();
        $facetFiltersToIncludeInResult = new FacetFiltersToIncludeInResult;
        $rowsPerPage = 100;
        $pageNumber = 0;
        $sortOrderConfig = $this->createStubSortOrderConfig($fieldCode, SortOrderDirection::ASC);

        $this->searchEngine->query(
            $searchCriteria,
            $filterSelection,
            $context,
            $facetFiltersToIncludeInResult,
            $rowsPerPage,
            $pageNumber,
            $sortOrderConfig
        );
    }
}
