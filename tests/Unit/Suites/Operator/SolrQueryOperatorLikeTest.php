<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorNotEqual
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
        return sprintf('%s:"*%s*"', $fieldName, $fieldValue);
    }
}
