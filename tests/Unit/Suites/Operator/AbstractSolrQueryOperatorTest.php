<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

abstract class AbstractSolrQueryOperatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SolrQueryOperator
     */
    private $operator;

    protected function setUp()
    {
        $this->operator = $this->getOperatorInstance();
    }

    public function testSolrQueryOperatorInterfaceIsImplemented()
    {
        $this->assertInstanceOf(SolrQueryOperator::class, $this->operator);
    }

    public function testFormattedQueryExpressionIsReturned()
    {
        $fieldName = 'foo';
        $fieldValue = 'bar';

        $expectedExpression = $this->getExpectedExpression($fieldName, $fieldValue);
        $result = $this->operator->getFormattedQueryString($fieldName, $fieldValue);

        $this->assertSame($expectedExpression, $result);
    }

    abstract protected function getOperatorInstance() : SolrQueryOperator;

    abstract protected function getExpectedExpression(string $fieldName, string $fieldValue) : string;
}
