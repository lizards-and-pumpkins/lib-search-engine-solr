<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortOrderConfig;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriteria;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\UnsupportedSearchCriteriaOperationException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperator;
use LizardsAndPumpkins\ProductSearch\QueryOptions;

class SolrQuery
{
    const SORTING_SUFFIX = '_sort';

    /**
     * @var SearchCriteria
     */
    private $criteria;

    /**
     * @var QueryOptions
     */
    private $queryOptions;

    /**
     * @var mixed[]
     */
    private $memoizedSolrQueryArrayRepresentation;

    public function __construct(SearchCriteria $criteria, QueryOptions $queryOptions)
    {
        $this->criteria = $criteria;
        $this->queryOptions = $queryOptions;
    }

    /**
     * @return string[]
     */
    public function toArray()
    {
        if (null === $this->memoizedSolrQueryArrayRepresentation) {
            $this->memoizedSolrQueryArrayRepresentation = $this->getSolrQueryArrayRepresentation();
        }

        return $this->memoizedSolrQueryArrayRepresentation;
    }

    /**
     * @return mixed[]
     */
    private function getSolrQueryArrayRepresentation()
    {
        $fieldsQueryString = $this->convertCriteriaIntoSolrQueryString($this->criteria);
        $contextQueryString = $this->convertContextIntoQueryString($this->queryOptions->getContext());

        $queryString = sprintf('(%s) AND %s', $fieldsQueryString, $contextQueryString);
        $rowsPerPage = $this->queryOptions->getRowsPerPage();
        $offset = $this->queryOptions->getPageNumber() * $rowsPerPage;
        $sortOrderString = $this->getSortOrderString($this->queryOptions->getSortOrderConfig());

        return [
            'q' => $queryString,
            'rows' => $rowsPerPage,
            'start' => $offset,
            'sort' => $sortOrderString,
        ];
    }

    /**
     * @param SearchCriteria $criteria
     * @return string
     */
    private function convertCriteriaIntoSolrQueryString(SearchCriteria $criteria)
    {
        $criteriaJson = json_encode($criteria);
        $criteriaArray = json_decode($criteriaJson, true);

        return $this->createSolrQueryStringFromCriteriaArray($criteriaArray);
    }

    /**
     * @param mixed[] $criteria
     * @return string
     */
    private function createSolrQueryStringFromCriteriaArray(array $criteria)
    {
        if (isset($criteria['condition'])) {
            $subCriteriaQueries = array_map([$this, 'createSolrQueryStringFromCriteriaArray'], $criteria['criteria']);
            $glue = ' ' . strtoupper($criteria['condition']) . ' ';
            return '(' . implode($glue, $subCriteriaQueries) . ')';
        }

        return $this->createPrimitiveOperationQueryString($criteria);
    }

    /**
     * @param string[] $criteria
     * @return string
     */
    private function createPrimitiveOperationQueryString(array $criteria)
    {
        $fieldName = $this->escapeQueryChars($criteria['fieldName']);
        $fieldValue = $this->escapeQueryChars($criteria['fieldValue']);
        $operator = $this->getSolrOperator($criteria['operation']);

        return $operator->getFormattedQueryString($fieldName, $fieldValue);
    }

    /**
     * @param string $operation
     * @return SolrQueryOperator
     */
    private function getSolrOperator($operation)
    {
        $className = __NAMESPACE__ . '\\Operator\\SolrQueryOperator' . $operation;

        if (!class_exists($className)) {
            throw new UnsupportedSearchCriteriaOperationException(
                sprintf('Unsupported criterion operation "%s".', $operation)
            );
        }

        return new $className;
    }

    /**
     * @param Context $context
     * @return string
     */
    private function convertContextIntoQueryString(Context $context)
    {
        return implode(' AND ', array_map(function ($contextCode) use ($context) {
            $fieldName = $this->escapeQueryChars($contextCode);
            $fieldValue = $this->escapeQueryChars($context->getValue($contextCode));
            return sprintf('((-%1$s:[* TO *] AND *:*) OR %1$s:"%2$s")', $fieldName, $fieldValue);
        }, $context->getSupportedCodes()));
    }

    /**
     * @param string $queryString
     * @return string
     */
    private function escapeQueryChars($queryString)
    {
        $src = ['\\', '+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '~', '*', '?', ':', '"', ';', '/'];

        $replace = array_map(function ($string) {
            return '\\' . $string;
        }, $src);

        return str_replace($src, $replace, $queryString);
    }

    /**
     * @param SortOrderConfig $sortOrderConfig
     * @return string
     */
    private function getSortOrderString(SortOrderConfig $sortOrderConfig)
    {
        return sprintf(
            '%s%s %s',
            $sortOrderConfig->getAttributeCode(),
            self::SORTING_SUFFIX,
            $sortOrderConfig->getSelectedDirection()
        );
    }
}
