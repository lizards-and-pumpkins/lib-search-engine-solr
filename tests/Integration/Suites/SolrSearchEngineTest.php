<?php

namespace LizardsAndPumpkins;

use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\SelfContainedContextBuilder;
use LizardsAndPumpkins\DataPool\SearchEngine\AbstractSearchEngineTest;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortOrderConfig;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortOrderDirection;
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
     * @return QueryOptions
     */
    private function createTestQueryOptions(SortOrderConfig $sortOrderConfig)
    {
        $filterSelection = [];
        $facetFiltersToIncludeInResult = new FacetFiltersToIncludeInResult();
        $rowsPerPage = 100;
        $pageNumber = 0;

        return QueryOptions::create(
            $filterSelection,
            $this->createTestContext(),
            $facetFiltersToIncludeInResult,
            $rowsPerPage,
            $pageNumber,
            $sortOrderConfig
        );
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

        $searchEngine->query($searchCriteria, $this->createTestQueryOptions($sortOrderConfig));
    }

    public function testExceptionIsThrownIfSolrIsNotAccessible()
    {
        $fieldCode = 'foo';
        $fieldValue = 'bar';

        $testSolrConnectionPath = 'http://localhost:8983/solr/nonexistingcore/';
        $client = new CurlSolrHttpClient($testSolrConnectionPath);

        $facetFieldTransformationRegistry = new FacetFieldTransformationRegistry();

        $searchCriteria = SearchCriterionEqual::create($fieldCode, $fieldValue);

        $searchEngine = new SolrSearchEngine($client, $searchCriteria, $facetFieldTransformationRegistry);

        $this->expectException(SolrConnectionException::class);
        $this->expectExceptionMessage('Error 404 Not Found');

        $searchCriteria = SearchCriterionEqual::create($fieldCode, $fieldValue);
        $sortOrderConfig = $this->createTestSortOrderConfig($fieldCode, SortOrderDirection::ASC);

        $searchEngine->query($searchCriteria, $this->createTestQueryOptions($sortOrderConfig));
    }
}
