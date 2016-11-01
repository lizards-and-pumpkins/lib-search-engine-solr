<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorAnything
 */
class SolrQueryOperatorAnythingTest extends AbstractSolrQueryOperatorTest
{
    final protected function getOperatorInstance() : SolrQueryOperator
    {
        return new SolrQueryOperatorAnything;
    }

    final protected function getExpectedExpression(string $fieldName, string $fieldValue) : string
    {
        return '*:*';
    }
}
