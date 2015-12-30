<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

interface SolrQueryOperator
{
    /**
     * @param string $fieldName
     * @param string $fieldValue
     * @return string
     */
    public function getFormattedQueryString($fieldName, $fieldValue);
}
