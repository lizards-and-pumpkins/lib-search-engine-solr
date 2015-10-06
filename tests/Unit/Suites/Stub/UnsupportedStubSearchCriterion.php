<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Stub;

use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterion;

class UnsupportedStubSearchCriterion extends SearchCriterion
{
    /**
     * @param string $searchDocumentFieldValue
     * @param string $criterionValue
     * @return bool
     */
    protected function hasValueMatchingOperator($searchDocumentFieldValue, $criterionValue)
    {
        return false;
    }
}
