<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorLessThan implements SolrQueryOperator
{
    public function getFormattedQueryString(string $fieldName, string $fieldValue) : string
    {
        return sprintf('(%1$s:[* TO %2$s] AND -%1$s:%2$s)', $fieldName, $fieldValue);
    }
}
