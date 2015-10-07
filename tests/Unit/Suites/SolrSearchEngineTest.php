<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\Context\ContextBuilder;
use LizardsAndPumpkins\Context\WebsiteContextDecorator;
use LizardsAndPumpkins\DataPool\SearchEngine\AbstractSearchEngineTest;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngine;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\SolrException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\UnsupportedSearchCriteriaOperationException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Stub\UnsupportedStubSearchCriterion;
use LizardsAndPumpkins\DataVersion;
use LizardsAndPumpkins\Product\ProductId;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrSearchEngine
 * @uses   \LizardsAndPumpkins\Context\ContextBuilder
 * @uses   \LizardsAndPumpkins\Context\ContextDecorator
 * @uses   \LizardsAndPumpkins\Context\LocaleContextDecorator
 * @uses   \LizardsAndPumpkins\Context\VersionedContext
 * @uses   \LizardsAndPumpkins\Context\WebsiteContextDecorator
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\CompositeSearchCriterion
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterion
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionGreaterOrEqualThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionGreaterThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionNotEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionLessOrEqualThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionLessThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentCollection
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentField
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorNotEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorGreaterThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorGreaterOrEqualThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLessThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperatorLessOrEqualThan
 * @uses   \LizardsAndPumpkins\DataVersion
 * @uses   \LizardsAndPumpkins\Product\ProductId
 */
class SolrSearchEngineTest extends AbstractSearchEngineTest
{
    /**
     * @param string[] $contextDataSet
     * @return Context
     */
    private function createContextFromDataParts(array $contextDataSet)
    {
        $dataVersion = DataVersion::fromVersionString('-1');
        $contextBuilder = new ContextBuilder($dataVersion);

        return $contextBuilder->createContext($contextDataSet);
    }

    /**
     * @return SearchEngine
     */
    protected function createSearchEngineInstance()
    {
        $testSolrConnectionPath = 'http://localhost.loc:8983/solr/';
        $testSearchableAttributes = ['foo', 'baz'];

        return new SolrSearchEngine($testSolrConnectionPath, $testSearchableAttributes);
    }

    public function testExceptionIsThrownIfSearchCriteriaOperationIsNotSupported()
    {
        $this->setExpectedException(UnsupportedSearchCriteriaOperationException::class);
        $searchCriteria = UnsupportedStubSearchCriterion::create('foo', 'bar');

        $searchEngine = $this->createSearchEngineInstance();
        $context = $this->createContextFromDataParts([WebsiteContextDecorator::CODE => 'website']);

        $searchEngine->getSearchDocumentsMatchingCriteria($searchCriteria, $context);
    }

    public function testExceptionIsThrownIfSolrQueryIsInvalid()
    {
        $searchEngine = $this->createSearchEngineInstance();
        $context = $this->createContextFromDataParts([WebsiteContextDecorator::CODE => 'website']);

        $productId = ProductId::fromString(uniqid());
        $fieldName = 'price';
        $fieldValue = 'foo';

        $searchDocumentFieldsCollection = SearchDocumentFieldCollection::fromArray([$fieldName => $fieldValue]);
        $searchDocument = new SearchDocument($searchDocumentFieldsCollection, $context, $productId);
        $searchDocumentCollection = new SearchDocumentCollection($searchDocument);

        $expectedExceptionMessage = sprintf(
            "Error 400 ERROR: [doc=%s] Error adding field '%s%s'='%s'",
            $productId,
            SolrSearchEngine::FIELD_PREFIX,
            $fieldName,
            $fieldValue
        );

        $this->setExpectedException(SolrException::class, $expectedExceptionMessage);

        $searchEngine->addSearchDocumentCollection($searchDocumentCollection);
    }
}
