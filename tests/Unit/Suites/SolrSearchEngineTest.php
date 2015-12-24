<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderConfig;
use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderDirection;
use LizardsAndPumpkins\ContentDelivery\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\ContextBuilder;
use LizardsAndPumpkins\Context\SelfContainedContextBuilder;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequest;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionEqual;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\UnsupportedSearchCriteriaOperationException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\SolrHttpClient;
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
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLike
 * @uses   \LizardsAndPumpkins\DataVersion
 * @uses   \LizardsAndPumpkins\Product\ProductId
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

    public function testExceptionIsThrownIfSearchCriteriaOperationIsNotSupported()
    {
        $fieldCode = 'foo';
        $fieldValue = 'bar';

        $this->setExpectedException(UnsupportedSearchCriteriaOperationException::class);
        $searchCriteria = UnsupportedStubSearchCriterion::create($fieldCode, $fieldValue);

        $filterSelection = [];
        $context = $this->createTestContext();
        $facetFilterRequest = new FacetFilterRequest;
        $rowsPerPage = 100;
        $pageNumber = 0;
        $sortOrderConfig = $this->createStubSortOrderConfig($fieldCode, SortOrderDirection::ASC);

        $this->searchEngine->getSearchDocumentsMatchingCriteria(
            $searchCriteria,
            $filterSelection,
            $context,
            $facetFilterRequest,
            $rowsPerPage,
            $pageNumber,
            $sortOrderConfig
        );
    }

    public function testExceptionIsThrownIfSolrResponseContainsErrorMessage()
    {
        $testErrorMessage = 'Test error message.';
        $this->stubSolrHttpClient->method('select')->willReturn(['error' => ['msg' => $testErrorMessage]]);

        $this->setExpectedException(SolrException::class, $testErrorMessage);

        $fieldCode = 'foo';
        $fieldValue = 'bar';

        $searchCriteria = SearchCriterionEqual::create($fieldCode, $fieldValue);
        $filterSelection = [];
        $context = $this->createTestContext();
        $facetFilterRequest = new FacetFilterRequest;
        $rowsPerPage = 100;
        $pageNumber = 0;
        $sortOrderConfig = $this->createStubSortOrderConfig($fieldCode, SortOrderDirection::ASC);

        $this->searchEngine->getSearchDocumentsMatchingCriteria(
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
