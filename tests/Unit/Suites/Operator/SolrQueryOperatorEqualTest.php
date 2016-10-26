<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorEqual
 */
class SolrQueryOperatorEqualTest extends AbstractSolrQueryOperatorTest
{
    final protected function getOperatorInstance() : SolrQueryOperator
    {
        return new SolrQueryOperatorEqual;
    }

    final protected function getExpectedExpression(string $fieldName, string $fieldValue) : string
    {
        return sprintf('%s:"%s"', $fieldName, $fieldValue);
    }
}
