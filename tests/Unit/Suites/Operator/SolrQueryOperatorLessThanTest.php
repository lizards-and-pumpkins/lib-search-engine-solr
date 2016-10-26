<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLessThan
 */
class SolrQueryOperatorLessThanTest extends AbstractSolrQueryOperatorTest
{
    final protected function getOperatorInstance() : SolrQueryOperator
    {
        return new SolrQueryOperatorLessThan;
    }

    final protected function getExpectedExpression(string $fieldName, string $fieldValue) : string
    {
        return sprintf('(%1$s:[* TO %2$s] AND -%1$s:%2$s)', $fieldName, $fieldValue);
    }
}
