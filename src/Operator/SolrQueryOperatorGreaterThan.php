<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorGreaterThan implements SolrQueryOperator
{
    /**
     * @inheritdoc
     */
    public function getFormattedQueryString($fieldName, $fieldValue)
    {
        return sprintf('(%1$s:[%2$s TO *] AND -%1$s:%2$s)', $fieldName, $fieldValue);
    }
}
