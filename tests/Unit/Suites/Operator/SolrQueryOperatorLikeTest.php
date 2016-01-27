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
}
