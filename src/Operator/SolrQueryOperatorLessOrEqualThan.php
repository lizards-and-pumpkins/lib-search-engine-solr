<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorLessOrEqualThan implements SolrQueryOperator
{
    public function getFormattedQueryString(string $fieldName, string $fieldValue) : string
    {
        return sprintf('%s:[* TO %s]', $fieldName, $fieldValue);
    }
}
