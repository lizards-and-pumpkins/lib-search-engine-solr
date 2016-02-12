<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderConfig;
use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderDirection;
use LizardsAndPumpkins\Context\Context;
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
     * @var Context|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stubContext;

    /**
     * @var SortOrderConfig|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stubSortOrderConfig;

    protected function setUp()
    {
        $this->stubCriteria = $this->getMock(CompositeSearchCriterion::class, [], [], '', false);
        $this->stubContext = $this->getMock(Context::class);
        $this->stubSortOrderConfig = $this->getMock(SortOrderConfig::class, [], [], '', false);
    }

    /**
     * @dataProvider nonIntegerProvider
     * @param mixed $invalidNumberOfRowsPerPage
     */
    public function testExceptionIsThrownIfNumberOfRowsPerPageIsNonInteger($invalidNumberOfRowsPerPage)
    {
        $this->setExpectedException(
            \InvalidArgumentException::class,
            sprintf('Number of rows per page must be an integer, got "%s".', gettype($invalidNumberOfRowsPerPage))
        );

        $pageNumber = 0;

        SolrQuery::create(
            $this->stubCriteria,
            $this->stubContext,
            $invalidNumberOfRowsPerPage,
            $pageNumber,
            $this->stubSortOrderConfig
        );
    }

    /**
     * @dataProvider nonPositiveIntegerProvider
     * @param mixed $invalidNumberOfRowsPerPage
     */
    public function testExceptionIsThrownIfNumberOfRowsPerPageIsNotPositive($invalidNumberOfRowsPerPage)
    {
        $this->setExpectedException(
            \InvalidArgumentException::class,
            sprintf('Number of rows per page must be greater than zero, got "%s".', $invalidNumberOfRowsPerPage)
        );

        $pageNumber = 0;

        SolrQuery::create(
            $this->stubCriteria,
            $this->stubContext,
            $invalidNumberOfRowsPerPage,
            $pageNumber,
            $this->stubSortOrderConfig
        );
    }

    /**
     * @dataProvider nonIntegerProvider
     * @param mixed $invalidCurrentPageNumber
     */
    public function testExceptionIsThrownIfCurrentPageNumberIsNonInteger($invalidCurrentPageNumber)
    {
        $this->setExpectedException(
            \InvalidArgumentException::class,
            sprintf('Current page number must be an integer, got "%s".', gettype($invalidCurrentPageNumber))
        );

        $prowPerPage = 10;

        SolrQuery::create(
            $this->stubCriteria,
            $this->stubContext,
            $prowPerPage,
            $invalidCurrentPageNumber,
            $this->stubSortOrderConfig
        );
    }

    public function testExceptionIsThrownIfCurrentPageNumberIsNegative()
    {
        $rowsPerPage = 10;
        $pageNumber = -1;

        $this->setExpectedException(
            \InvalidArgumentException::class,
            sprintf('Current page number must be greater or equal to zero, got "%s".', $pageNumber)
        );

        SolrQuery::create(
            $this->stubCriteria,
            $this->stubContext,
            $rowsPerPage,
            $pageNumber,
            $this->stubSortOrderConfig
        );
    }

    /**
     * @return array[]
     */
    public function nonIntegerProvider()
    {
        return [
            [[]],
            [''],
            [new \stdClass()],
            [false],
            [null]
        ];
    }

    /**
     * @return array[]
     */
    public function nonPositiveIntegerProvider()
    {
        return [
            [0],
            [-18],
        ];
    }

    public function testExceptionIsThrownIfSolrOperationIsUnknown()
    {
        $this->setExpectedException(UnsupportedSearchCriteriaOperationException::class);

        $criteriaAsArray = [
            'fieldName' => 'foo',
            'fieldValue' => 'bar',
            'operation' => 'non-existing-operation'
        ];
        $this->stubCriteria->method('jsonSerialize')->willReturn($criteriaAsArray);

        $rowsPerPage = 10;
        $pageNumber = 0;

        SolrQuery::create(
            $this->stubCriteria,
            $this->stubContext,
            $rowsPerPage,
            $pageNumber,
            $this->stubSortOrderConfig
        );
    }

    public function testArrayRepresentationOfQueryIsReturned()
    {
        $criteriaAsArray = [
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
        ];
        $this->stubCriteria->method('jsonSerialize')->willReturn($criteriaAsArray);

        $this->stubContext->method('getSupportedCodes')->willReturn(['qux']);
        $this->stubContext->method('getValue')->willReturnMap([['qux', 2]]);

        $rowsPerPage = 10;
        $pageNumber = 2;

        $this->stubSortOrderConfig->method('getAttributeCode')->willReturn('foo');
        $this->stubSortOrderConfig->method('getSelectedDirection')->willReturn(SortOrderDirection::ASC);

        $query = SolrQuery::create(
            $this->stubCriteria,
            $this->stubContext,
            $rowsPerPage,
            $pageNumber,
            $this->stubSortOrderConfig
        );

        $expectedQueryParametersArray = [
            'q' => '((foo:"bar" OR baz:[1 TO *])) AND ((-qux:[* TO *] AND *:*) OR qux:"2")',
            'rows' => $rowsPerPage,
            'start' => $rowsPerPage * $pageNumber,
            'sort' => 'foo asc',
        ];

        $this->assertSame($expectedQueryParametersArray, $query->toArray());
    }

    public function testQueryStringIsEscaped()
    {
        $criteriaAsArray = [
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
        ];
        $this->stubCriteria->method('jsonSerialize')->willReturn($criteriaAsArray);

        $this->stubContext->method('getSupportedCodes')->willReturn(['qu+x']);
        $this->stubContext->method('getValue')->willReturnMap([['qu+x', 2]]);

        $rowsPerPage = 10;
        $pageNumber = 2;

        $this->stubSortOrderConfig->method('getAttributeCode')->willReturn('foo');
        $this->stubSortOrderConfig->method('getSelectedDirection')->willReturn(SortOrderDirection::ASC);

        $query = SolrQuery::create(
            $this->stubCriteria,
            $this->stubContext,
            $rowsPerPage,
            $pageNumber,
            $this->stubSortOrderConfig
        );

        $expectedQueryParametersArray = [
            'q' => '((fo\/o:"ba\\\\r" OR baz:"\[\]")) AND ((-qu\+x:[* TO *] AND *:*) OR qu\+x:"2")',
            'rows' => $rowsPerPage,
            'start' => $rowsPerPage * $pageNumber,
            'sort' => 'foo asc',
        ];

        $this->assertSame($expectedQueryParametersArray, $query->toArray());
    }
}
