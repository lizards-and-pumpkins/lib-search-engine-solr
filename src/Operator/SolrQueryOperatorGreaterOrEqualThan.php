<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorGreaterOrEqualThan implements SolrQueryOperator
{
    /**
     * @inheritdoc
     */
    public function getFormattedQueryString($fieldName, $fieldValue)
    {
        return sprintf('%s:[%s TO *]', urlencode($fieldName), urlencode($fieldValue));
    }
}
