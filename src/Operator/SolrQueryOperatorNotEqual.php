<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorNotEqual implements SolrQueryOperator
{
    /**
     * @inheritdoc
     */
    public function getFormattedQueryString($fieldName, $fieldValue)
    {
        return sprintf('(-%s:"%s" AND *:*)', urlencode($fieldName), urlencode($fieldValue));
    }
}
