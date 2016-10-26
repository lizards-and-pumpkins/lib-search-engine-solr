<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortOrderConfig;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortOrderDirection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\CompositeSearchCriterion;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriteria;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\UnsupportedSearchCriteriaOperationException;
use LizardsAndPumpkins\Import\Product\AttributeCode;
use LizardsAndPumpkins\ProductSearch\QueryOptions;

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
        $this->stubCriteria = $this->createMock(CompositeSearchCriterion::class);
        $this->stubQueryOptions = $this->createMock(QueryOptions::class);
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

    public function testArrayRepresentationOfQueryContainsFormattedQueryString()
    {
        $this->stubCriteria->expects($this->once())->method('jsonSerialize')->willReturn([
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

        $stubContext = $this->createMock(Context::class);
        $stubContext->method('getSupportedCodes')->willReturn(['qux']);
        $stubContext->method('getValue')->willReturnMap([['qux', 2]]);
        $this->stubQueryOptions->method('getContext')->willReturn($stubContext);

        $stubSortOrderConfig = $this->createMock(SortOrderConfig::class);
        $this->stubQueryOptions->method('getSortOrderConfig')->willReturn($stubSortOrderConfig);

        $result = $this->solrQuery->toArray();
        $expectedQueryString = '((foo:"bar" OR baz:[1 TO *])) AND ((-qux:[* TO *] AND *:*) OR qux:"2")';

        $this->assertArrayHasKey('q', $result);
        $this->assertSame($expectedQueryString, $result['q']);
    }

    public function testArrayRepresentationOfQueryContainsNumberOdRowsPerPage()
    {
        $this->stubCriteria->expects($this->once())->method('jsonSerialize')->willReturn([
            'fieldName' => 'foo',
            'fieldValue' => 'bar',
            'operation' => 'Equal'
        ]);

        $stubContext = $this->createMock(Context::class);
        $stubContext->method('getSupportedCodes')->willReturn([]);
        $this->stubQueryOptions->method('getContext')->willReturn($stubContext);

        $stubSortOrderConfig = $this->createMock(SortOrderConfig::class);
        $this->stubQueryOptions->method('getSortOrderConfig')->willReturn($stubSortOrderConfig);

        $rowsPerPage = 10;
        $this->stubQueryOptions->method('getRowsPerPage')->willReturn($rowsPerPage);

        $result = $this->solrQuery->toArray();

        $this->assertArrayHasKey('rows', $result);
        $this->assertSame($rowsPerPage, $result['rows']);
    }

    public function testArrayRepresentationOfQueryContainsSearchResultsOffset()
    {
        $this->stubCriteria->expects($this->once())->method('jsonSerialize')->willReturn([
            'fieldName' => 'foo',
            'fieldValue' => 'bar',
            'operation' => 'Equal'
        ]);

        $stubContext = $this->createMock(Context::class);
        $stubContext->method('getSupportedCodes')->willReturn([]);
        $this->stubQueryOptions->method('getContext')->willReturn($stubContext);

        $stubSortOrderConfig = $this->createMock(SortOrderConfig::class);
        $this->stubQueryOptions->method('getSortOrderConfig')->willReturn($stubSortOrderConfig);

        $rowsPerPage = 10;
        $this->stubQueryOptions->method('getRowsPerPage')->willReturn($rowsPerPage);

        $pageNumber = 2;
        $this->stubQueryOptions->method('getPageNumber')->willReturn($pageNumber);

        $result = $this->solrQuery->toArray();

        $this->assertArrayHasKey('start', $result);
        $this->assertSame($rowsPerPage * $pageNumber, $result['start']);
    }

    public function testArrayRepresentationOfQueryContainsSortOrderString()
    {
        $this->stubCriteria->expects($this->once())->method('jsonSerialize')->willReturn([
            'fieldName' => 'foo',
            'fieldValue' => 'bar',
            'operation' => 'Equal'
        ]);

        $stubContext = $this->createMock(Context::class);
        $stubContext->method('getSupportedCodes')->willReturn([]);
        $this->stubQueryOptions->method('getContext')->willReturn($stubContext);

        $sortAttributeCode = AttributeCode::fromString('baz');
        $sortDirection = SortOrderDirection::create(SortOrderDirection::ASC);

        $stubSortOrderConfig = $this->createMock(SortOrderConfig::class);
        $stubSortOrderConfig->method('getAttributeCode')->willReturn($sortAttributeCode);
        $stubSortOrderConfig->method('getSelectedDirection')->willReturn($sortDirection);
        $this->stubQueryOptions->method('getSortOrderConfig')->willReturn($stubSortOrderConfig);

        $result = $this->solrQuery->toArray();
        $expectedSortOrderString = sprintf('%s%s %s', $sortAttributeCode, SolrQuery::SORTING_SUFFIX, $sortDirection);

        $this->assertArrayHasKey('sort', $result);
        $this->assertSame($expectedSortOrderString, $result['sort']);
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

        $stubContext = $this->createMock(Context::class);
        $stubContext->method('getSupportedCodes')->willReturn(['qu+x']);
        $stubContext->method('getValue')->willReturnMap([['qu+x', 2]]);
        $this->stubQueryOptions->method('getContext')->willReturn($stubContext);

        $stubSortOrderConfig = $this->createMock(SortOrderConfig::class);
        $this->stubQueryOptions->method('getSortOrderConfig')->willReturn($stubSortOrderConfig);

        $result = $this->solrQuery->toArray();
        $expectedQueryString = '((fo\/o:"ba\\\\r" OR baz:"\[\]")) AND ((-qu\+x:[* TO *] AND *:*) OR qu\+x:"2")';

        $this->assertSame($expectedQueryString, $result['q']);
    }

    public function testArrayRepresentationOfSolrQueryIsMemoized()
    {
        $this->stubCriteria->expects($this->once())->method('jsonSerialize')->willReturn([
            'fieldName' => 'foo',
            'fieldValue' => 'bar',
            'operation' => 'Equal'
        ]);

        $stubContext = $this->createMock(Context::class);
        $stubContext->method('getSupportedCodes')->willReturn([]);
        $this->stubQueryOptions->method('getContext')->willReturn($stubContext);

        $stubSortOrderConfig = $this->createMock(SortOrderConfig::class);
        $this->stubQueryOptions->method('getSortOrderConfig')->willReturn($stubSortOrderConfig);

        $resultA = $this->solrQuery->toArray();
        $resultB = $this->solrQuery->toArray();

        $this->assertSame($resultA, $resultB);
    }
}
