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
     * @return array Query results as associative array
     */
    public function fetchAll(): array;

    /**
     * Get single result as associative array
     *
     * @return array|null Single row or null if no result
     */
    public function fetch(): ?array;

    /**
     * Get single result as object
     *
     * @return stdClass|null Query result or null if no result
     */
    public function fetchObject(): ?stdClass;

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

    /**
     * Begin transaction
     */
    public function beginTransaction(): void;

    /**
     * Commit transaction
     */
    public function commit(): void;

    /**
     * Rollback transaction
     */
    public function rollBack(): void;
}