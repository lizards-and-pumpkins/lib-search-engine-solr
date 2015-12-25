<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentField;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http\SolrHttpClient;

class SolrWriter
{
    /**
     * @var SolrHttpClient
     */
    private $client;

    public function __construct(SolrHttpClient $client)
    {
        $this->client = $client;
    }

    public function addSearchDocumentsCollectionToSolr(SearchDocumentCollection $documentsCollection)
    {
        $documents = array_map([$this, 'convertSearchDocumentToArray'], iterator_to_array($documentsCollection));
        $this->client->update($documents);
    }

    public function deleteAllDocuments()
    {
        $request = ['delete' => ['query' => '*:*']];
        $this->client->update($request);
    }

    /**
     * @param SearchDocument $document
     * @return string[]
     */
    private function convertSearchDocumentToArray(SearchDocument $document)
    {
        $context = $document->getContext();

        return array_merge(
            [
                SolrSearchEngine::DOCUMENT_ID_FIELD_NAME => $document->getProductId() . '_' . $context,
                SolrSearchEngine::PRODUCT_ID_FIELD_NAME => (string) $document->getProductId()
            ],
            $this->getSearchDocumentFields($document->getFieldsCollection()),
            $this->getContextFields($context)
        );
    }

    /**
     * @param SearchDocumentFieldCollection $fieldCollection
     * @return array[]
     */
    private function getSearchDocumentFields(SearchDocumentFieldCollection $fieldCollection)
    {
        return array_reduce($fieldCollection->getFields(), function ($carry, SearchDocumentField $field) {
            return array_merge([$field->getKey() => $field->getValues()], $carry);
        }, []);
    }

    /**
     * @param Context $context
     * @return string[]
     */
    private function getContextFields(Context $context)
    {
        return array_reduce($context->getSupportedCodes(), function ($carry, $contextCode) use ($context) {
            return array_merge([$contextCode => $context->getValue($contextCode)], $carry);
        }, []);
    }
}
