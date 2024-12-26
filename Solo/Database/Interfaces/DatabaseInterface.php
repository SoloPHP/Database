<?php

namespace Solo\Database\Interfaces;

use Exception;
use stdClass;

/**
 * Database interface
 */
interface DatabaseInterface
{
    /**
     * Execute SQL query
     *
     * @param string $sql SQL query with placeholders
     * @param mixed ...$params Parameters to replace placeholders
     * @throws Exception When query execution fails
     * @return self For method chaining
     */
    public function query(string $sql, mixed ...$params): self;

    /**
     * Prepare SQL query with placeholders
     *
     * @param string $sql SQL query
     * @param mixed ...$params Parameters to replace placeholders
     * @return string Prepared SQL query
     */
    public function prepare(string $sql, mixed ...$params): string;

    /**
     * Get all results
     *
     * @param string $primaryKey Optional primary key for array keys
     * @return array<int|string, stdClass> Query results as array of objects
     */
    public function fetchAll(string $primaryKey = ''): array;

    /**
     * Get single result as associative array
     *
     * @param string|null $column Optional specific column to fetch
     * @return array<string, mixed>|string|int|float|bool|null Single row or column value
     * @throws Exception When specified column not found
     */
    public function fetchAssoc(?string $column = null): array|string|int|float|bool|null;

    /**
     * Get single result
     *
     * @param string|null $column Optional specific column to fetch
     * @throws Exception When specified column not found
     * @return stdClass|string|int|float|bool|null Query result
     */
    public function fetchObject(?string $column = null): stdClass|string|int|float|bool|null;

    /**
     * Get last inserted ID
     *
     * @return string|false Last inserted ID or false on failure
     */
    public function lastInsertId(): string|false;

    /**
     * Get number of affected rows
     *
     * @return int Number of affected rows
     */
    public function rowCount(): int;
}