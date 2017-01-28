<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

use LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrSearchEngine;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorFullText
 */
class SolrQueryOperatorFullTextTest extends AbstractSolrQueryOperatorTest
{
    final protected function getOperatorInstance() : SolrQueryOperator
    {
        return new SolrQueryOperatorFullText();
    }

    final protected function getExpectedExpression(string $fieldName, string $fieldValue) : string
    {
        return sprintf('(%s:"%s")', SolrSearchEngine::FULL_TEXT_SEARCH_FIELD_NAME, $fieldValue);
    }
}
