<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\Context\SelfContainedContext;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;
use LizardsAndPumpkins\Product\ProductId;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrDocumentBuilder
 */
class SolrDocumentBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testSearchDocumentIsConvertedIntoSolrFormat()
    {
        $documentFieldName = 'foo';
        $documentFieldValue = 'bar';
        $searchDocumentFieldCollection = SearchDocumentFieldCollection::fromArray(
            [$documentFieldName => $documentFieldValue]
        );

        $contextPartName = 'baz';
        $contextPartValue = 'qux';
        $context = SelfContainedContext::fromArray([$contextPartName => $contextPartValue]);

        $productId = ProductId::fromString(uniqid());

        $searchDocument = new SearchDocument($searchDocumentFieldCollection, $context, $productId);

        $documentUniqueId = sprintf('%s_%s:%s', (string) $productId, $contextPartName, $contextPartValue);
        $expectedSolrDocument = [
            SolrSearchEngine::DOCUMENT_ID_FIELD_NAME => $documentUniqueId,
            SolrSearchEngine::PRODUCT_ID_FIELD_NAME  => (string) $productId,
            $documentFieldName => [$documentFieldValue],
            $contextPartName => $contextPartValue,
        ];

        $this->assertSame($expectedSolrDocument, SolrDocumentBuilder::fromSearchDocument($searchDocument));
    }
}
