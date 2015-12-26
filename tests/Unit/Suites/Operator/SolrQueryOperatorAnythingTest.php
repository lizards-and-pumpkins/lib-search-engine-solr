<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorAnything
 */
class SolrQueryOperatorAnythingTest extends AbstractSolrQueryOperatorTest
{
    /**
     * {@inheritdoc}
     */
    final protected function getOperatorInstance()
    {
        return new SolrQueryOperatorAnything;
    }

    /**
     * {@inheritdoc}
     */
    final protected function getExpectedExpression($fieldName, $fieldValue)
    {
        return '*:*';
    }
}
