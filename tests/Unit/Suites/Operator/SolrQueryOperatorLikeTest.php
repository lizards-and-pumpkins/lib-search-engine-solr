<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLike
 */
class SolrQueryOperatorLikeTest extends AbstractSolrQueryOperatorTest
{
    final protected function getOperatorInstance() : SolrQueryOperator
    {
        return new SolrQueryOperatorLike;
    }

    final protected function getExpectedExpression(string $fieldName, string $fieldValue) : string
    {
        $values = explode(' ', $fieldValue);
        $queries = array_map(function ($value) use ($fieldName) {
            return sprintf('(%s:"%s")', $fieldName, $value);
        }, $values);

        return implode(' AND ', $queries);
    }

    public function testNoEmptyCriteriaIsCreateFromSpacesSurroundingFieldValue()
    {
        $fieldName = 'foo';
        $fieldValue = ' bar ';

        $operator = $this->getOperatorInstance();
        $result = $operator->getFormattedQueryString($fieldName, $fieldValue);
        $expectedQueryString = sprintf('(%s:"%s")', $fieldName, trim($fieldValue));

        $this->assertSame($expectedQueryString, $result);
    }
}
