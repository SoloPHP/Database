<?php declare(strict_types=1);

namespace Solo;

use PDO;
use PDOStatement;
use PDOException;
use Exception;
use Solo\Database\Connection;
use Solo\Database\QueryBuilder;

/**
 * Main database class for query execution
 */
class Database
{
    private PDO $pdo;
    private ?PDOStatement $stmt = null;
    private QueryBuilder $queryBuilder;
    private ?Logger $logger;

    /**
     * Initialize database handler
     */
    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->getPdo();
        $this->logger = $connection->getLogger();
        $this->queryBuilder = new QueryBuilder(
            $this->pdo,
            $connection->getPrefix(),
            $this->logger
        );
    }

    /**
     * Execute SQL query
     *
     * @param string $sql SQL query with placeholders
     * @param mixed ...$params Parameters to replace placeholders
     * @throws Exception When query execution fails
     * @return self For method chaining
     */
    public function query(string $sql, ...$params): self
    {
        try {
            $parsedSql = $this->queryBuilder->build($sql, ...$params);
            $this->stmt = $this->pdo->prepare($parsedSql);
            $this->stmt->execute();
        } catch (PDOException $e) {
            $this->logger?->error('Query failed: ' . $e->getMessage(), [
                'sql' => $sql,
                'params' => $params
            ]);
            throw new Exception('Query failed: ' . $e->getMessage());
        }
        return $this;
    }

    /**
     * Build SQL query with placeholders
     */
    public function build(string $sql, ...$params): string
    {
        return $this->queryBuilder->build($sql, ...$params);
    }

    /**
     * Get all results
     *
     * @param string $primaryKey Optional primary key for array keys
     * @return array Query results
     */
    public function results(string $primaryKey = ''): array
    {
        $results = $this->stmt->fetchAll($this->pdo::FETCH_CLASS);

        if (!$primaryKey) {
            return $results;
        }

        return array_column($results, null, $primaryKey);
    }

    /**
     * Get single result
     *
     * @param string|null $column Optional specific column to fetch
     * @throws Exception When specified column not found
     * @return mixed Query result
     */
    public function result(?string $column = null)
    {
        if (!$column) {
            return $this->stmt->fetchObject();
        }

        $resultArray = $this->stmt->fetch(PDO::FETCH_ASSOC);
        if (!isset($resultArray[$column])) {
            $message = sprintf("Column '%s' not found in result set", $column);
            $this->logger?->error($message);
            throw new Exception($message);
        }

        return $resultArray[$column];
    }

    /**
     * Get last inserted ID
     *
     * @return string|false Last inserted ID or false on failure
     */
    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Get number of affected rows
     */
    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    /**
     * Clean up statement
     */
    public function __destruct()
    {
        $this->stmt = null;
    }
}