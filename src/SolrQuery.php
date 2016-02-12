<?php

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr;

use LizardsAndPumpkins\ContentDelivery\Catalog\SortOrderConfig;
use LizardsAndPumpkins\Context\Context;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriteria;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Exception\UnsupportedSearchCriteriaOperationException;
use LizardsAndPumpkins\DataPool\SearchEngine\Solr\Operator\SolrQueryOperator;

class SolrQuery
{
    /**
     * @var string
     */
    private $queryString;

    /**
     * @var int
     */
    private $rowsPerPage;

    /**
     * @var int
     */
    private $offest;

    /**
     * @var string
     */
    private $sortOrderString;

    /**
     * @param string $queryString
     * @param int $rowsPerPage
     * @param int $offset
     * @param string $sortOrderString
     */
    private function __construct($queryString, $rowsPerPage, $offset, $sortOrderString)
    {
        $this->queryString = $queryString;
        $this->rowsPerPage = $rowsPerPage;
        $this->offest = $offset;
        $this->sortOrderString = $sortOrderString;
    }

    /**
     * @param SearchCriteria $criteria
     * @param Context $context
     * @param int $rowsPerPage
     * @param int $pageNumber
     * @param SortOrderConfig $sortOrderConfig
     * @return SolrQuery
     */
    public static function create(
        SearchCriteria $criteria,
        Context $context,
        $rowsPerPage,
        $pageNumber,
        SortOrderConfig $sortOrderConfig
    ) {
        self::validateNumberOfRowsPerPage($rowsPerPage);
        self::validateCurrentPageNumber($pageNumber);

        $fieldsQueryString = self::convertCriteriaIntoSolrQueryString($criteria);
        $contextQueryString = self::convertContextIntoQueryString($context);

        $queryString = sprintf('(%s) AND %s', $fieldsQueryString, $contextQueryString);
        $offset = $pageNumber * $rowsPerPage;
        $sortOrderString = $sortOrderConfig->getAttributeCode() . ' ' . $sortOrderConfig->getSelectedDirection();

        return new self($queryString, $rowsPerPage, $offset, $sortOrderString);
    }

    /**
     * @param int $rowsPerPage
     */
    private static function validateNumberOfRowsPerPage($rowsPerPage)
    {
        if (!is_int($rowsPerPage)) {
            throw new \InvalidArgumentException(
                sprintf('Number of rows per page must be an integer, got "%s".', gettype($rowsPerPage))
            );
        }

        if ($rowsPerPage < 1) {
            throw new \InvalidArgumentException(
                sprintf('Number of rows per page must be greater than zero, got "%s".', $rowsPerPage)
            );
        }
    }

    /**
     * @return string
     */
    public function toArray()
    {
        return [
            'q' => $this->queryString,
            'rows' => $this->rowsPerPage,
            'start' => $this->offest,
            'sort' => $this->sortOrderString,
        ];
    }

    /**
     * @param SearchCriteria $criteria
     * @return string
     */
    private static function convertCriteriaIntoSolrQueryString(SearchCriteria $criteria)
    {
        $criteriaJson = json_encode($criteria);
        $criteriaArray = json_decode($criteriaJson, true);

        return self::createSolrQueryStringFromCriteriaArray($criteriaArray);
    }

    /**
     * @param mixed[] $criteria
     * @return string
     */
    private static function createSolrQueryStringFromCriteriaArray(array $criteria)
    {
        if (isset($criteria['condition'])) {
            $subCriteriaQueries = array_map(
                [SolrQuery::class, 'createSolrQueryStringFromCriteriaArray'],
                $criteria['criteria']
            );
            $glue = ' ' . strtoupper($criteria['condition']) . ' ';
            return '(' . implode($glue, $subCriteriaQueries) . ')';
        }

        return self::createPrimitiveOperationQueryString($criteria);
    }

    /**
     * @param string[] $criteria
     * @return string
     */
    private static function createPrimitiveOperationQueryString(array $criteria)
    {
        $fieldName = self::escapeQueryChars($criteria['fieldName']);
        $fieldValue = self::escapeQueryChars($criteria['fieldValue']);
        $operator = self::getSolrOperator($criteria['operation']);

        return $operator->getFormattedQueryString($fieldName, $fieldValue);
    }

    /**
     * @param string $operation
     * @return SolrQueryOperator
     */
    private static function getSolrOperator($operation)
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
    private static function convertContextIntoQueryString(Context $context)
    {
        return implode(' AND ', array_map(function ($contextCode) use ($context) {
            $fieldName = self::escapeQueryChars($contextCode);
            $fieldValue = self::escapeQueryChars($context->getValue($contextCode));
            return sprintf('((-%1$s:[* TO *] AND *:*) OR %1$s:"%2$s")', $fieldName, $fieldValue);
        }, $context->getSupportedCodes()));
    }

    /**
     * @param int $pageNumber
     */
    private static function validateCurrentPageNumber($pageNumber)
    {
        if (!is_int($pageNumber)) {
            throw new \InvalidArgumentException(
                sprintf('Current page number must be an integer, got "%s".', gettype($pageNumber))
            );
        }

        if ($pageNumber < 0) {
            throw new \InvalidArgumentException(
                sprintf('Current page number must be greater or equal to zero, got "%s".', $pageNumber)
            );
        }
    }

    /**
     * @param string $queryString
     * @return string
     */
    private static function escapeQueryChars($queryString)
    {
        $src = ['\\', '+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '~', '*', '?', ':', '"', ';', '/'];

        $replace = array_map(function ($string) {
            return '\\' . $string;
        }, $src);

        return str_replace($src, $replace, $queryString);
    }
}
