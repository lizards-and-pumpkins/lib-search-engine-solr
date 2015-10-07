<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorGreaterOrEqualThan
 */
class SolrQueryOperatorGreaterOrEqualThanTest extends AbstractSolrQueryOperatorTest
{
    /**
     * @inheritdoc
     */
    final protected function getOperatorInstance()
    {
        return new SolrQueryOperatorGreaterOrEqualThan;
    }

    /**
     * @inheritdoc
     */
    final protected function getExpectedExpression($fieldName, $fieldValue)
    {
        return sprintf('%s:[%s TO *]', $fieldName, $fieldValue);
    }
}
