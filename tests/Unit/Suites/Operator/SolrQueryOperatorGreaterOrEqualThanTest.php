<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorGreaterOrEqualThan
 */
class SolrQueryOperatorGreaterOrEqualThanTest extends AbstractSolrQueryOperatorTest
{
    final protected function getOperatorInstance() : SolrQueryOperator
    {
        return new SolrQueryOperatorGreaterOrEqualThan;
    }

    final protected function getExpectedExpression(string $fieldName, string $fieldValue) : string
    {
        return sprintf('%s:[%s TO *]', $fieldName, $fieldValue);
    }
}
