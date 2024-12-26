<?php

namespace Solo\Database\Interfaces;

interface QueryBuilderInterface
{
    /**
     * Prepare an SQL query with placeholders replaced by actual values.
     *
     * @param string $sql SQL query with placeholders
     * @param mixed ...$params Parameters to replace placeholders
     * @return string Built SQL query
     * @throws Exception When placeholder count doesn't match parameter count
     */
    public function prepare(string $sql, ...$params): string;

}