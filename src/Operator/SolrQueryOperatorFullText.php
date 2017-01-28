<?php

declare(strict_types = 1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator;

use LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrSearchEngine;

class SolrQueryOperatorFullText implements SolrQueryOperator
{
    public function getFormattedQueryString(string $fieldName, string $fieldValue): string
    {
        return sprintf('(%s:"%s")', SolrSearchEngine::FULL_TEXT_SEARCH_FIELD_NAME, $fieldValue);
    }
}
