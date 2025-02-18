<?php

namespace Solo\Database\Interfaces;

use Exception;
use stdClass;

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
     * @param int|null $fetchMode Optional fetch mode (PDO::FETCH_ASSOC or PDO::FETCH_OBJ)
     * @return array<int|string, array|stdClass> Query results as array of arrays or objects
     */
    public function fetchAll(?int $fetchMode = null): array;

    /**
     * Get single result
     *
     * @param int|null $fetchMode Optional fetch mode (PDO::FETCH_ASSOC or PDO::FETCH_OBJ)
     * @return array|stdClass|null Single row as array or object, or null if no result
     */
    public function fetch(?int $fetchMode = null): array|stdClass|null;

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