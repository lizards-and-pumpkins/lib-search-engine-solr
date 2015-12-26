<?php

namespace LizardsAndPumpkins;

use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderConfig;
use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderDirection;
use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\ContextBuilder;
use LizardsAndPumpkins\Context\SelfContainedContextBuilder;
use LizardsAndPumpkins\DataPool\SearchEngine\AbstractSearchEngineTest;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionEqual;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngine;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\CurlSolrHttpClient;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\Exception\SolrConnectionException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrSearchEngine;
use LizardsAndPumpkins\Product\AttributeCode;
use LizardsAndPumpkins\Utils\Clearable;

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
        $testSolrConnectionPath = 'http://localhost:8983/solr/techproducts/';
        $client = new CurlSolrHttpClient($testSolrConnectionPath);

        $testSearchableAttributes = ['foo', 'baz'];

        return new SolrSearchEngine(
            $client,
            $testSearchableAttributes,
            $facetFieldTransformationRegistry
        );
    }

    public function testExceptionIsThrownIfSolrQueryIsInvalid()
    {
        $nonExistingFieldCode = 'foooooooo';
        $fieldValue = 'whatever';

        $facetFieldTransformationRegistry = new FacetFieldTransformationRegistry();
        $searchEngine = $this->createSearchEngineInstance($facetFieldTransformationRegistry);

        $searchCriteria = SearchCriterionEqual::create($nonExistingFieldCode, $fieldValue);

        $expectedExceptionMessage = sprintf('undefined field %s', $nonExistingFieldCode);
        $this->setExpectedException(SolrException::class, $expectedExceptionMessage);

        $filterSelection = [];
        $context = $this->createTestContext();
        $facetFiltersToIncludeInResult = new FacetFiltersToIncludeInResult;
        $rowsPerPage = 100;
        $pageNumber = 0;
        $sortOrderConfig = $this->createTestSortOrderConfig($nonExistingFieldCode, SortOrderDirection::ASC);

        $searchEngine->query(
            $searchCriteria,
            $filterSelection,
            $context,
            $facetFiltersToIncludeInResult,
            $rowsPerPage,
            $pageNumber,
            $sortOrderConfig
        );
    }

    public function testExceptionIsThrownIfSolrIsNotAccessible()
    {
        $fieldCode = 'foo';
        $fieldValue = 'bar';

        $testSolrConnectionPath = 'http://localhost:8983/solr/nonexistingcore/';
        $client = new CurlSolrHttpClient($testSolrConnectionPath);

        $testSearchableAttributes = ['foo', 'baz'];
        $facetFieldTransformationRegistry = new FacetFieldTransformationRegistry();

        $searchEngine = new SolrSearchEngine(
            $client,
            $testSearchableAttributes,
            $facetFieldTransformationRegistry
        );

        $expectedExceptionMessage = 'Error 404 Not Found';
        $this->setExpectedException(SolrConnectionException::class, $expectedExceptionMessage);

        $searchCriteria = SearchCriterionEqual::create($fieldCode, $fieldValue);
        $filterSelection = [];
        $context = $this->createTestContext();
        $facetFiltersToIncludeInResult = new FacetFiltersToIncludeInResult;
        $rowsPerPage = 100;
        $pageNumber = 0;
        $sortOrderConfig = $this->createTestSortOrderConfig($fieldCode, SortOrderDirection::ASC);

        $searchEngine->query(
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
