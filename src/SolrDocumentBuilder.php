<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentField;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;

class SolrDocumentBuilder
{
    /**
     * @param SearchDocument $document
     * @return string[]
     */
    public static function fromSearchDocument(SearchDocument $document) : array
    {
        $context = $document->getContext();

        return array_merge(
            [
                SolrSearchEngine::DOCUMENT_ID_FIELD_NAME => $document->getProductId() . '_' . $context,
                SolrSearchEngine::PRODUCT_ID_FIELD_NAME => (string) $document->getProductId()
            ],
            self::getSearchDocumentFields($document->getFieldsCollection()),
            self::getContextFields($context)
        );
    }

    /**
     * @param SearchDocumentFieldCollection $fieldCollection
     * @return array[]
     */
    private static function getSearchDocumentFields(SearchDocumentFieldCollection $fieldCollection) : array
    {
        return array_reduce($fieldCollection->getFields(), function ($carry, SearchDocumentField $field) {
            return array_merge([$field->getKey() => $field->getValues()], $carry);
        }, []);
    }

    /**
     * @param Context $context
     * @return string[]
     */
    private static function getContextFields(Context $context) : array
    {
        return array_reduce($context->getSupportedCodes(), function ($carry, $contextCode) use ($context) {
            return array_merge([$contextCode => $context->getValue($contextCode)], $carry);
        }, []);
    }
}
