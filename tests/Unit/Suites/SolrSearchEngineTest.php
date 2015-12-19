<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderConfig;
use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderDirection;
use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\ContextBuilder;
use LizardsAndPumpkins\Context\SelfContainedContextBuilder;
use LizardsAndPumpkins\DataPool\SearchEngine\AbstractSearchEngineTest;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequest;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionEqual;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngine;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\UnsupportedSearchCriteriaOperationException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Stub\UnsupportedStubSearchCriterion;
use LizardsAndPumpkins\Product\AttributeCode;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrSearchEngine
 * @uses   \LizardsAndPumpkins\Context\ContextBuilder
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\CompositeSearchCriterion
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterion
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionGreaterOrEqualThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionGreaterThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionNotEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionLessOrEqualThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionLessThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentCollection
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentField
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorNotEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorGreaterThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorGreaterOrEqualThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLessThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLessOrEqualThan
 * @uses   \LizardsAndPumpkins\DataVersion
 * @uses   \LizardsAndPumpkins\Product\ProductId
 */
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
     * @param FacetFieldTransformationRegistry $facetFieldTransformationRegistry
     * @return SearchEngine
     */
    final protected function createSearchEngineInstance(
        FacetFieldTransformationRegistry $facetFieldTransformationRegistry
    ) {
        $testSolrConnectionPath = 'http://localhost:8983/solr/techproducts/';
        $testSearchableAttributes = ['foo', 'baz'];

        return new SolrSearchEngine(
            $testSolrConnectionPath,
            $testSearchableAttributes,
            $facetFieldTransformationRegistry
        );
    }

    public function testExceptionIsThrownIfSearchCriteriaOperationIsNotSupported()
    {
        $fieldCode = 'foo';
        $fieldValue = 'bar';

        $this->setExpectedException(UnsupportedSearchCriteriaOperationException::class);
        $searchCriteria = UnsupportedStubSearchCriterion::create($fieldCode, $fieldValue);

        $stubFacetFieldTransformationRegistry = $this->getMock(FacetFieldTransformationRegistry::class);

        $searchEngine = $this->createSearchEngineInstance($stubFacetFieldTransformationRegistry);

        $filterSelection = [];
        $context = $this->createTestContext();
        $facetFilterRequest = new FacetFilterRequest;
        $rowsPerPage = 100;
        $pageNumber = 0;
        $sortOrderConfig = $this->createStubSortOrderConfig($fieldCode, SortOrderDirection::ASC);

        $searchEngine->getSearchDocumentsMatchingCriteria(
            $searchCriteria,
            $filterSelection,
            $context,
            $facetFilterRequest,
            $rowsPerPage,
            $pageNumber,
            $sortOrderConfig
        );
    }

    public function testExceptionIsThrownIfSolrQueryIsInvalid()
    {
        $fieldCode = 'non-existing-field-name';
        $fieldValue = 'whatever';

        $stubFacetFieldTransformationRegistry = $this->getMock(FacetFieldTransformationRegistry::class);

        $searchEngine = $this->createSearchEngineInstance($stubFacetFieldTransformationRegistry);

        $searchCriteria = SearchCriterionEqual::create($fieldCode, $fieldValue);

        $expectedExceptionMessage = sprintf('undefined field %s', $fieldCode);
        $this->setExpectedException(SolrException::class, $expectedExceptionMessage);

        $filterSelection = [];
        $context = $this->createTestContext();
        $facetFilterRequest = new FacetFilterRequest;
        $rowsPerPage = 100;
        $pageNumber = 0;
        $sortOrderConfig = $this->createStubSortOrderConfig($fieldCode, SortOrderDirection::ASC);

        $searchEngine->getSearchDocumentsMatchingCriteria(
            $searchCriteria,
            $filterSelection,
            $context,
            $facetFilterRequest,
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
        $testSearchableAttributes = ['foo', 'baz'];
        $stubTransformationRegistry = $this->getMock(FacetFieldTransformationRegistry::class);

        $searchEngine = new SolrSearchEngine(
            $testSolrConnectionPath,
            $testSearchableAttributes,
            $stubTransformationRegistry
        );

        $expectedExceptionMessage = 'Error 404 Not Found';
        $this->setExpectedException(SolrException::class, $expectedExceptionMessage);

        $searchCriteria = SearchCriterionEqual::create($fieldCode, $fieldValue);
        $filterSelection = [];
        $context = $this->createTestContext();
        $facetFilterRequest = new FacetFilterRequest;
        $rowsPerPage = 100;
        $pageNumber = 0;
        $sortOrderConfig = $this->createStubSortOrderConfig($fieldCode, SortOrderDirection::ASC);

        $searchEngine->getSearchDocumentsMatchingCriteria(
            $searchCriteria,
            $filterSelection,
            $context,
            $facetFilterRequest,
            $rowsPerPage,
            $pageNumber,
            $sortOrderConfig
        );
    }
}
