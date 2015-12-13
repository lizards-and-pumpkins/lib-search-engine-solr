<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorLike implements SolrQueryOperator
{
    /**
     * {@inheritdoc}
     */
    public function getFormattedQueryString($fieldName, $fieldValue)
    {
        return sprintf('%s:"*%s*"', urlencode($fieldName), urlencode($fieldValue));
    }
}
