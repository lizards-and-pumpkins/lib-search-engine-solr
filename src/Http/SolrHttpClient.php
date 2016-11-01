<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Solr\Http;

interface SolrHttpClient
{
    const UPDATE_SERVLET = 'update';
    const SEARCH_SERVLET = 'select';

    /**
     * @param mixed[] $parameters
     * @return mixed
     */
    public function update(array $parameters);

    /**
     * @param mixed[] $parameters
     * @return mixed
     */
    public function select(array $parameters);
}
