<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\DataPool\SearchEngine\Query\SortBy;
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
     * @return mixed[]
     */
    public function toArray() : array
    {
        if (null === $this->memoizedSolrQueryArrayRepresentation) {
            $this->memoizedSolrQueryArrayRepresentation = $this->getSolrQueryArrayRepresentation();
        }

        return $this->memoizedSolrQueryArrayRepresentation;
    }

    /**
     * @return mixed[]
     */
    private function getSolrQueryArrayRepresentation() : array
    {
        $fieldsQueryString = $this->convertCriteriaIntoSolrQueryString($this->criteria);
        $contextQueryString = $this->convertContextIntoQueryString($this->queryOptions->getContext());

        $queryString = sprintf('(%s) AND %s', $fieldsQueryString, $contextQueryString);
        $rowsPerPage = $this->queryOptions->getRowsPerPage();
        $offset = $this->queryOptions->getPageNumber() * $rowsPerPage;
        $sortOrderString = $this->getSortOrderString($this->queryOptions->getSortBy());

        return [
            'q' => $queryString,
            'rows' => $rowsPerPage,
            'start' => $offset,
            'sort' => $sortOrderString,
        ];
    }

    private function convertCriteriaIntoSolrQueryString(SearchCriteria $criteria) : string
    {
        $criteriaJson = json_encode($criteria);
        $criteriaArray = json_decode($criteriaJson, true);

        return $this->createSolrQueryStringFromCriteriaArray($criteriaArray);
    }

    /**
     * @param mixed[] $criteria
     * @return string
     */
    private function createSolrQueryStringFromCriteriaArray(array $criteria) : string
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
    private function createPrimitiveOperationQueryString(array $criteria) : string
    {
        $fieldName = $this->escapeQueryChars($criteria['fieldName']);
        $fieldValue = $this->escapeQueryChars($criteria['fieldValue']);
        $operator = $this->getSolrOperator($criteria['operation']);

        return $operator->getFormattedQueryString($fieldName, $fieldValue);
    }

    private function getSolrOperator(string $operation) : SolrQueryOperator
    {
        $className = __NAMESPACE__ . '\\Operator\\SolrQueryOperator' . $operation;

        if (!class_exists($className)) {
            throw new UnsupportedSearchCriteriaOperationException(
                sprintf('Unsupported criterion operation "%s".', $operation)
            );
        }

        return new $className;
    }

    private function convertContextIntoQueryString(Context $context) : string
    {
        return implode(' AND ', array_map(function ($contextCode) use ($context) {
            $fieldName = $this->escapeQueryChars($contextCode);
            $fieldValue = $this->escapeQueryChars($context->getValue($contextCode));
            return sprintf('((-%1$s:[* TO *] AND *:*) OR %1$s:"%2$s")', $fieldName, $fieldValue);
        }, $context->getSupportedCodes()));
    }

    /**
     * @param mixed $queryString
     * @return string
     */
    private function escapeQueryChars($queryString) : string
    {
        $src = ['\\', '+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '~', '*', '?', ':', '"', ';', '/'];

        $replace = array_map(function (string $string) {
            return '\\' . $string;
        }, $src);

        return str_replace($src, $replace, $queryString);
    }

    private function getSortOrderString(SortBy $sortOrderConfig) : string
    {
        return sprintf(
            '%s%s %s',
            $sortOrderConfig->getAttributeCode(),
            self::SORTING_SUFFIX,
            $sortOrderConfig->getSelectedDirection()
        );
    }
}
