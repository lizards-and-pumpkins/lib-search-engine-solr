<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorEqual implements SolrQueryOperator
{
    /**
     * @inheritdoc
     */
    public function getFormattedQueryString($fieldName, $fieldValue)
    {
        return sprintf('%s:"%s"', $fieldName, $fieldValue);
    }
}
