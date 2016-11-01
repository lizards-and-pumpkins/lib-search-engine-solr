<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorNotEqual
 */
class SolrQueryOperatorNotEqualTest extends AbstractSolrQueryOperatorTest
{
    final protected function getOperatorInstance() : SolrQueryOperator
    {
        return new SolrQueryOperatorNotEqual;
    }

    final protected function getExpectedExpression(string $fieldName, string $fieldValue) : string
    {
        return sprintf('(-%s:"%s" AND *:*)', $fieldName, $fieldValue);
    }
}
