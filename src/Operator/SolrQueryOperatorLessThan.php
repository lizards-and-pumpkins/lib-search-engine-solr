<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorLessThan implements SolrQueryOperator
{
    /**
     * {@inheritdoc}
     */
    public function getFormattedQueryString($fieldName, $fieldValue)
    {
        return sprintf('(%1$s:[* TO %2$s] AND -%1$s:%2$s)', urlencode($fieldName), urlencode($fieldValue));
    }
}
