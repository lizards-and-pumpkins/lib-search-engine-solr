<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLike
 */
class SolrQueryOperatorLikeTest extends AbstractSolrQueryOperatorTest
{
    /**
     * {@inheritdoc}
     */
    final protected function getOperatorInstance()
    {
        return new SolrQueryOperatorLike;
    }

    /**
     * {@inheritdoc}
     */
    final protected function getExpectedExpression($fieldName, $fieldValue)
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
