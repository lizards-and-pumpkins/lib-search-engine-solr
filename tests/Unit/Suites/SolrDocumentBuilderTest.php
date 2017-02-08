<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\Context\SelfContainedContext;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;
use LizardsAndPumpkins\Import\Product\ProductId;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrDocumentBuilder
 */
class SolrDocumentBuilderTest extends TestCase
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
        $context = new SelfContainedContext([$contextPartName => $contextPartValue]);

        $productId = new ProductId(uniqid());

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
