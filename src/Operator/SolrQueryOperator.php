<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

interface SolrQueryOperator
{
    public function getFormattedQueryString(string $fieldName, string $fieldValue) : string;
}
