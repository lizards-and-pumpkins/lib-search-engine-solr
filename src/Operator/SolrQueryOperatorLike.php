<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorLike implements SolrQueryOperator
{
    public function getFormattedQueryString(string $fieldName, string $fieldValue) : string
    {
        $values = array_filter(explode(' ', $fieldValue));
        $queries = array_map(function ($value) use ($fieldName) {
            return sprintf('(%s:"%s")', $fieldName, $value);
        }, $values);

        return implode(' AND ', $queries);
    }
}
