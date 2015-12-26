<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorAnything implements SolrQueryOperator
{
    /**
     * @param string $fieldName
     * @param string $fieldValue
     * @return string
     */
    public function getFormattedQueryString($fieldName, $fieldValue)
    {
        return '*:*';
    }
}
