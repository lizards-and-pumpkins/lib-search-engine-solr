<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

use LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrSearchEngine;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorNotEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLike
 */
class SolrQueryOperatorLikeTest extends AbstractSolrQueryOperatorTest
{
    /**
     * {@inheritdoc}
     */
    final protected function getOperatorInstance()
    {
        return new SolrQueryOperatorLike;
    }

    /**
     * {@inheritdoc}
     */
    final protected function getExpectedExpression($fieldName, $fieldValue)
    {
        return sprintf('%s%s:"*%s*"', $fieldName, SolrSearchEngine::TOKENIZED_FIELD_SUFFIX, $fieldValue);
    }
}
