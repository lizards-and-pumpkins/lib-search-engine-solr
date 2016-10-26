<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLessOrEqualThan
 */
class SolrQueryOperatorLessOrEqualThanTest extends AbstractSolrQueryOperatorTest
{
    final protected function getOperatorInstance() : SolrQueryOperator
    {
        return new SolrQueryOperatorLessOrEqualThan;
    }

    final protected function getExpectedExpression(string $fieldName, string $fieldValue) : string
    {
        return sprintf('%s:[* TO %s]', $fieldName, $fieldValue);
    }
}
