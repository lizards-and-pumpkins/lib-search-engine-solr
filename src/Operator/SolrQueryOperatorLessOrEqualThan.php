<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorLessOrEqualThan implements SolrQueryOperator
{
    /**
     * @inheritdoc
     */
    public function getFormattedQueryString($fieldName, $fieldValue)
    {
        return sprintf('%s:[* TO %s]', urlencode($fieldName), urlencode($fieldValue));
    }
}
