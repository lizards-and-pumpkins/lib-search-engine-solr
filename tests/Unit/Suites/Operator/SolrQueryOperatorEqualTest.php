<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorEqual
 */
class SolrQueryOperatorEqualTest extends AbstractSolrQueryOperatorTest
{
    /**
     * @inheritdoc
     */
    final protected function getOperatorInstance()
    {
        return new SolrQueryOperatorEqual;
    }

    /**
     * @inheritdoc
     */
    final protected function getExpectedExpression($fieldName, $fieldValue)
    {
        return sprintf('%s:"%s"', $fieldName, $fieldValue);
    }
}
