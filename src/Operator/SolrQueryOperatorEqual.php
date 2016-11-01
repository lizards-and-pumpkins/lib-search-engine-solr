<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorEqual implements SolrQueryOperator
{
    public function getFormattedQueryString(string $fieldName, string $fieldValue) : string
    {
        return sprintf('%s:"%s"', $fieldName, $fieldValue);
    }
}
