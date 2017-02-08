<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

use PHPUnit\Framework\TestCase;

abstract class AbstractSolrQueryOperatorTest extends TestCase
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
