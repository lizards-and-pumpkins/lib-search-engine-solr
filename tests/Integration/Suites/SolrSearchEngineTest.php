<?php

namespace LizardsAndPumpkins;

use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\SelfContainedContextBuilder;
use LizardsAndPumpkins\DataPool\SearchEngine\AbstractSearchEngineTest;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortOrderConfig;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortOrderDirection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriteria;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionAnything;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionEqual;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngine;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\CurlSolrHttpClient;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\Exception\SolrConnectionException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrSearchEngine;
use LizardsAndPumpkins\Import\Product\AttributeCode;
use LizardsAndPumpkins\ProductSearch\QueryOptions;
use LizardsAndPumpkins\Util\Config\EnvironmentConfigReader;
use LizardsAndPumpkins\Util\Storage\Clearable;

class SolrSearchEngineTest extends AbstractSearchEngineTest
{
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
     * @return SortOrderConfig
     */
    private function createTestSortOrderConfig($sortByFieldCode, $sortDirection)
    {
        return SortOrderConfig::createSelected(
            AttributeCode::fromString($sortByFieldCode),
            SortOrderDirection::create($sortDirection)
        );
    }

    /**
     * @param SortOrderConfig $sortOrderConfig
     * @return QueryOptions|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createStubQueryOptions(SortOrderConfig $sortOrderConfig)
    {
        $facetFiltersToIncludeInResult = new FacetFiltersToIncludeInResult();

        $stubQueryOptions = $this->getMock(QueryOptions::class, [], [], '', false);
        $stubQueryOptions->method('getFilterSelection')->willReturn([]);
        $stubQueryOptions->method('getContext')->willReturn($this->createTestContext());
        $stubQueryOptions->method('getFacetFiltersToIncludeInResult')->willReturn($facetFiltersToIncludeInResult);
        $stubQueryOptions->method('getRowsPerPage')->willReturn(100);
        $stubQueryOptions->method('getPageNumber')->willReturn(0);
        $stubQueryOptions->method('getSortOrderConfig')->willReturn($sortOrderConfig);

        return $stubQueryOptions;
    }

    protected function tearDown()
    {
        $facetFieldTransformationRegistry = new FacetFieldTransformationRegistry();
        $this->createSearchEngineInstance($facetFieldTransformationRegistry)->clear();
    }

    /**
     * @param FacetFieldTransformationRegistry $facetFieldTransformationRegistry
     * @return SearchEngine|Clearable
     */
    final protected function createSearchEngineInstance(
        FacetFieldTransformationRegistry $facetFieldTransformationRegistry
    ) {
        $config = EnvironmentConfigReader::fromGlobalState();
        $testSolrConnectionPath = $config->get('solr_integration_test_connection_path');

        $client = new CurlSolrHttpClient($testSolrConnectionPath);

        $globalProductListingCriteria = SearchCriterionAnything::create();

        return new SolrSearchEngine($client, $globalProductListingCriteria, $facetFieldTransformationRegistry);
    }

    public function testExceptionIsThrownIfSolrQueryIsInvalid()
    {
        $nonExistingFieldCode = 'foooooooo';
        $fieldValue = 'whatever';

        $facetFieldTransformationRegistry = new FacetFieldTransformationRegistry();
        $searchEngine = $this->createSearchEngineInstance($facetFieldTransformationRegistry);

        $searchCriteria = SearchCriterionEqual::create($nonExistingFieldCode, $fieldValue);
        $sortOrderConfig = $this->createTestSortOrderConfig($nonExistingFieldCode, SortOrderDirection::ASC);

        $this->expectException(SolrException::class);
        $this->expectExceptionMessage(sprintf('undefined field %s', $nonExistingFieldCode));

        $searchEngine->query($searchCriteria, $this->createStubQueryOptions($sortOrderConfig));
    }

    public function testExceptionIsThrownIfSolrIsNotAccessible()
    {
        $fieldCode = 'foo';
        $fieldValue = 'bar';

        $testSolrConnectionPath = 'http://localhost:8983/solr/nonexistingcore/';
        $client = new CurlSolrHttpClient($testSolrConnectionPath);

        $facetFieldTransformationRegistry = new FacetFieldTransformationRegistry();

        /** @var SearchCriteria|\PHPUnit_Framework_MockObject_MockObject $stubCriteria */
        $stubCriteria = $this->getMock(SearchCriteria::class);

        $searchEngine = new SolrSearchEngine($client, $stubCriteria, $facetFieldTransformationRegistry);

        $this->expectException(SolrConnectionException::class);
        $this->expectExceptionMessage('Error 404 Not Found');

        $searchCriteria = SearchCriterionEqual::create($fieldCode, $fieldValue);
        $sortOrderConfig = $this->createTestSortOrderConfig($fieldCode, SortOrderDirection::ASC);

        $searchEngine->query($searchCriteria, $this->createStubQueryOptions($sortOrderConfig));
    }
}
