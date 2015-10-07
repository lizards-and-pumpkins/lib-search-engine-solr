<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLessOrEqualThan
 */
class SolrQueryOperatorLessOrEqualThanTest extends AbstractSolrQueryOperatorTest
{
    /**
     * @inheritdoc
     */
    final protected function getOperatorInstance()
    {
        return new SolrQueryOperatorLessOrEqualThan;
    }

    /**
     * @inheritdoc
     */
    final protected function getExpectedExpression($fieldName, $fieldValue)
    {
        return sprintf('%s:[* TO %s]', $fieldName, $fieldValue);
    }
}
