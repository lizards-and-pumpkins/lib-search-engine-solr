<?php

declare(strict_types=1);

namespace LizardsAndPumpkins;

use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\SelfContainedContextBuilder;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult;
use LizardsAndPumpkins\DataPool\SearchEngine\IntegrationTest\AbstractSearchEngineTest;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortBy;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortDirection;
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
    private function createTestContext() : Context
    {
        return SelfContainedContextBuilder::rehydrateContext([
            'website' => 'website',
            'version' => '-1'
        ]);
    }

    private function createTestSortOrderConfig(string $sortByFieldCode, string $sortDirection) : SortBy
    {
        return new SortBy(AttributeCode::fromString($sortByFieldCode), SortDirection::create($sortDirection));
    }

    private function createTestQueryOptions(SortBy $sortOrderConfig) : QueryOptions
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
    ) : SearchEngine {
        $config = EnvironmentConfigReader::fromGlobalState();
        $testSolrConnectionPath = $config->get('solr_integration_test_connection_path');

        $client = new CurlSolrHttpClient($testSolrConnectionPath);

        return new SolrSearchEngine($client, $facetFieldTransformationRegistry);
    }

    public function testExceptionIsThrownIfSolrQueryIsInvalid()
    {
        $nonExistingFieldCode = 'foooooooo';
        $fieldValue = 'whatever';

        $facetFieldTransformationRegistry = new FacetFieldTransformationRegistry();
        $searchEngine = $this->createSearchEngineInstance($facetFieldTransformationRegistry);

        $searchCriteria = new SearchCriterionEqual($nonExistingFieldCode, $fieldValue);
        $sortOrderConfig = $this->createTestSortOrderConfig($nonExistingFieldCode, SortDirection::ASC);

        $this->expectException(SolrException::class);
        $this->expectExceptionMessage(sprintf('undefined field %s', $nonExistingFieldCode));

        $searchEngine->query($searchCriteria, $this->createTestQueryOptions($sortOrderConfig));
    }

    /**
     * @dataProvider invalidConnectionPathProvider
     */
    public function testExceptionIsThrownIfSolrIsNotAccessible(string $invalidSolrConnectionPath)
    {
        $fieldCode = 'foo';
        $fieldValue = 'bar';

        $client = new CurlSolrHttpClient($invalidSolrConnectionPath);

        $facetFieldTransformationRegistry = new FacetFieldTransformationRegistry();

        $searchEngine = new SolrSearchEngine($client, $facetFieldTransformationRegistry);

        $this->expectException(SolrConnectionException::class);

        $searchCriteria = new SearchCriterionEqual($fieldCode, $fieldValue);
        $sortOrderConfig = $this->createTestSortOrderConfig($fieldCode, SortDirection::ASC);

        $searchEngine->query($searchCriteria, $this->createTestQueryOptions($sortOrderConfig));
    }

    public function invalidConnectionPathProvider(): array
    {
        $config = EnvironmentConfigReader::fromGlobalState();
        $testSolrConnectionPath = $config->get('solr_integration_test_connection_path');

        return [
            'non-existing-core' => [preg_replace('#[^/]+/$#', 'nonexistingcore/', $testSolrConnectionPath)],
            'invalid-port' => [preg_replace('/:[0-9]+/', ':1', $testSolrConnectionPath)],
        ];
    }
}
