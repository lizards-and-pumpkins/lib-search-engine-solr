<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderConfig;
use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderDirection;
use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\DataPool\SearchEngine\QueryOptions;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\CompositeSearchCriterion;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriteria;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\UnsupportedSearchCriteriaOperationException;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrQuery
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorGreaterOrEqualThan
 */
class SolrQueryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SearchCriteria|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stubCriteria;

    /**
     * @var QueryOptions|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stubQueryOptions;

    /**
     * @var SolrQuery
     */
    private $solrQuery;

    protected function setUp()
    {
        $this->stubCriteria = $this->getMock(CompositeSearchCriterion::class, [], [], '', false);
        $this->stubQueryOptions = $this->getMock(QueryOptions::class, [], [], '', false);
        $this->solrQuery = new SolrQuery($this->stubCriteria, $this->stubQueryOptions);
    }

    public function testExceptionIsThrownIfSolrOperationIsUnknown()
    {
        $this->expectException(UnsupportedSearchCriteriaOperationException::class);

        $this->stubCriteria->method('jsonSerialize')->willReturn([
            'fieldName' => 'foo',
            'fieldValue' => 'bar',
            'operation' => 'non-existing-operation'
        ]);

        $this->solrQuery->toArray();
    }

    public function testArrayRepresentationOfQueryIsReturned()
    {
        $this->stubCriteria->method('jsonSerialize')->willReturn([
            'condition' => CompositeSearchCriterion::OR_CONDITION,
            'criteria' => [
                [
                    'operation' => 'Equal',
                    'fieldName' => 'foo',
                    'fieldValue' => 'bar',
                ],
                [
                    'operation' => 'GreaterOrEqualThan',
                    'fieldName' => 'baz',
                    'fieldValue' => 1,
                ],
            ]
        ]);

        $stubContext = $this->getMock(Context::class);
        $stubContext->method('getSupportedCodes')->willReturn(['qux']);
        $stubContext->method('getValue')->willReturnMap([['qux', 2]]);

        $rowsPerPage = 10;
        $pageNumber = 2;

        $stubSortOrderConfig = $this->getMock(SortOrderConfig::class, [], [], '', false);
        $stubSortOrderConfig->method('getAttributeCode')->willReturn('foo');
        $stubSortOrderConfig->method('getSelectedDirection')->willReturn(SortOrderDirection::ASC);

        $this->stubQueryOptions->method('getContext')->willReturn($stubContext);
        $this->stubQueryOptions->method('getRowsPerPage')->willReturn($rowsPerPage);
        $this->stubQueryOptions->method('getPageNumber')->willReturn($pageNumber);
        $this->stubQueryOptions->method('getSortOrderConfig')->willReturn($stubSortOrderConfig);

        $expectedQueryParametersArray = [
            'q' => '((foo:"bar" OR baz:[1 TO *])) AND ((-qux:[* TO *] AND *:*) OR qux:"2")',
            'rows' => $rowsPerPage,
            'start' => $rowsPerPage * $pageNumber,
            'sort' => 'foo asc',
        ];

        $this->assertSame($expectedQueryParametersArray, $this->solrQuery->toArray());
    }

    public function testQueryStringIsEscaped()
    {
        $this->stubCriteria->method('jsonSerialize')->willReturn([
            'condition' => CompositeSearchCriterion::OR_CONDITION,
            'criteria' => [
                [
                    'operation' => 'Equal',
                    'fieldName' => 'fo/o',
                    'fieldValue' => 'ba\r',
                ],
                [
                    'operation' => 'Equal',
                    'fieldName' => 'baz',
                    'fieldValue' => '[]',
                ],
            ]
        ]);

        $stubContext = $this->getMock(Context::class);
        $stubContext->method('getSupportedCodes')->willReturn(['qu+x']);
        $stubContext->method('getValue')->willReturnMap([['qu+x', 2]]);

        $rowsPerPage = 10;
        $pageNumber = 2;

        $stubSortOrderConfig = $this->getMock(SortOrderConfig::class, [], [], '', false);
        $stubSortOrderConfig->method('getAttributeCode')->willReturn('foo');
        $stubSortOrderConfig->method('getSelectedDirection')->willReturn(SortOrderDirection::ASC);

        $this->stubQueryOptions->method('getContext')->willReturn($stubContext);
        $this->stubQueryOptions->method('getRowsPerPage')->willReturn($rowsPerPage);
        $this->stubQueryOptions->method('getPageNumber')->willReturn($pageNumber);
        $this->stubQueryOptions->method('getSortOrderConfig')->willReturn($stubSortOrderConfig);

        $expectedQueryParametersArray = [
            'q' => '((fo\/o:"ba\\\\r" OR baz:"\[\]")) AND ((-qu\+x:[* TO *] AND *:*) OR qu\+x:"2")',
            'rows' => $rowsPerPage,
            'start' => $rowsPerPage * $pageNumber,
            'sort' => 'foo asc',
        ];

        $this->assertSame($expectedQueryParametersArray, $this->solrQuery->toArray());
    }

    public function testArrayRepresentationOfSolrQueryIsMemoized()
    {
        $this->stubCriteria->expects($this->once())->method('jsonSerialize')->willReturn([
            'fieldName' => 'foo',
            'fieldValue' => 'bar',
            'operation' => 'Equal'
        ]);

        $stubContext = $this->getMock(Context::class);
        $stubContext->method('getSupportedCodes')->willReturn([]);
        $this->stubQueryOptions->method('getContext')->willReturn($stubContext);

        $stubSortOrderConfig = $this->getMock(SortOrderConfig::class, [], [], '', false);
        $this->stubQueryOptions->method('getSortOrderConfig')->willReturn($stubSortOrderConfig);

        $resultA = $this->solrQuery->toArray();
        $resultB = $this->solrQuery->toArray();

        $this->assertSame($resultA, $resultB);
    }
}
