<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

use LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrSearchEngine;

class SolrQueryOperatorLike implements SolrQueryOperator
{
    /**
     * {@inheritdoc}
     */
    public function getFormattedQueryString($fieldName, $fieldValue)
    {
        return sprintf('%s%s:"%s"', $fieldName, SolrSearchEngine::TOKENIZED_FIELD_SUFFIX, $fieldValue);
    }
}
