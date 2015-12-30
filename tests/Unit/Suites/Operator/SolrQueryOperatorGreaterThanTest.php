<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorGreaterThan
 */
class SolrQueryOperatorGreaterThanTest extends AbstractSolrQueryOperatorTest
{
    /**
     * {@inheritdoc}
     */
    final protected function getOperatorInstance()
    {
        return new SolrQueryOperatorGreaterThan;
    }

    /**
     * {@inheritdoc}
     */
    final protected function getExpectedExpression($fieldName, $fieldValue)
    {
        return sprintf('(%1$s:[%2$s TO *] AND -%1$s:%2$s)', $fieldName, $fieldValue);
    }
}
