<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\Context\SelfContainedContext;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\SolrHttpClient;
use LizardsAndPumpkins\Product\ProductId;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Solr\SolrWriter
 */
class SolrWriterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SolrWriter
     */
    private $writer;

    /**
     * @var SolrHttpClient|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockHttpClient;

    protected function setUp()
    {
        $this->mockHttpClient = $this->getMock(SolrHttpClient::class, [], [], '', false);
        $this->writer = new SolrWriter($this->mockHttpClient);
    }

    public function testUpdateRequestWithDocumentsIsPassedToHttpClient()
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
        $searchDocumentCollection = new SearchDocumentCollection($searchDocument);

        $documentUniqueId = sprintf('%s_%s:%s', (string) $productId, $contextPartName, $contextPartValue);
        $expectedDocumentsArrayRepresentation = [[
            SolrSearchEngine::DOCUMENT_ID_FIELD_NAME => $documentUniqueId,
            SolrSearchEngine::PRODUCT_ID_FIELD_NAME  => (string) $productId,
            $documentFieldName => [$documentFieldValue],
            $contextPartName => $contextPartValue,
        ]];
        $this->mockHttpClient->expects($this->once())->method('update')->with($expectedDocumentsArrayRepresentation);

        $this->writer->addSearchDocumentsCollectionToSolr($searchDocumentCollection);
    }
    
    public function testUpdateRequestDeletingAllDocumentsInStoreIsPassedToHttpClient()
    {
        $this->mockHttpClient->expects($this->once())->method('update')->with(['delete' => ['query' => '*:*']]);
        $this->writer->deleteAllDocuments();
    }
}
