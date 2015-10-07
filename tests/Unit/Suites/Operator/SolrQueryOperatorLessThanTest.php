<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLessThan
 */
class SolrQueryOperatorLessThanTest extends AbstractSolrQueryOperatorTest
{
    /**
     * @inheritdoc
     */
    final protected function getOperatorInstance()
    {
        return new SolrQueryOperatorLessThan;
    }

    /**
     * @inheritdoc
     */
    final protected function getExpectedExpression($fieldName, $fieldValue)
    {
        return sprintf('(%1$s:[* TO %2$s] AND -%1$s:%2$s)', $fieldName, $fieldValue);
    }
}
