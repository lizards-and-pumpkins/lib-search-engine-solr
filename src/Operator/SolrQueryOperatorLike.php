<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

class SolrQueryOperatorLike implements SolrQueryOperator
{
    /**
     * {@inheritdoc}
     */
    public function getFormattedQueryString($fieldName, $fieldValue)
    {
        $values = array_filter(explode(' ', $fieldValue));
        $queries = array_map(function ($value) use ($fieldName) {
            return sprintf('(%s:"%s")', $fieldName, $value);
        }, $values);

        return implode(' AND ', $queries);
    }
}
