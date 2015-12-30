<?php

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

    public function testQueryIsGuardedAgainstSpecialCharacters()
    {
        $fieldName = 'fo"o';
        $encodedFieldName = 'fo%22o';
        $fieldValue = 'b(a):[r]"';
        $encodedFieldValue = 'b%28a%29%3A%5Br%5D%22';

        $expectedExpression = $this->getExpectedExpression($encodedFieldName, $encodedFieldValue);
        $result = $this->operator->getFormattedQueryString($fieldName, $fieldValue);

        $this->assertSame($expectedExpression, $result);
    }

    /**
     * @return SolrQueryOperator
     */
    abstract protected function getOperatorInstance();

    /**
     * @param string $fieldName
     * @param string $fieldValue
     * @return string
     */
    abstract protected function getExpectedExpression($fieldName, $fieldValue);
}