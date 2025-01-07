<?php declare(strict_types=1);

namespace Solo;

use PDO;
use PDOStatement;
use PDOException;
use Exception;
use Solo\Database\Connection;
use Solo\Database\Interfaces\DatabaseInterface;
use Solo\Database\QueryBuilder;
use stdClass;

/**
 * Main database class for query execution
 */
final class Database implements DatabaseInterface
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

    public function query(string $sql, mixed ...$params): self
    {
        try {
            $parsedSql = $this->queryBuilder->prepare($sql, ...$params);
            $this->stmt = $this->pdo->prepare($parsedSql);
            $this->stmt->execute();
        } catch (PDOException $e) {
            $this->logger?->error('Query failed: ' . $e->getMessage(), [
                'sql' => $sql,
                'params' => $params
            ]);
            throw new Exception('Query failed: ' . $e->getMessage(), (int)$e->getCode());
        }
        return $this;
    }

    public function prepare(string $sql, mixed ...$params): string
    {
        return $this->queryBuilder->prepare($sql, ...$params);
    }

    public function fetchAll(string $primaryKey = ''): array
    {
        $results = $this->stmt->fetchAll($this->pdo::FETCH_CLASS);

        if (!$primaryKey) {
            return $results;
        }

        return array_column($results, null, $primaryKey);
    }

    public function fetchAssoc(?string $column = null): array|string|int|float|bool|null
    {
        $result = $this->stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        if (!$column) {
            return $result;
        }

        if (!isset($result[$column])) {
            $message = sprintf("Column '%s' not found in result set", $column);
            $this->logger?->error($message);
            throw new Exception($message);
        }

        return $result[$column];
    }

    public function fetchObject(?string $column = null): stdClass|string|int|float|bool|null
    {
        if (!$column) {
            return $this->stmt->fetchObject();
        }

        $resultArray = $this->stmt->fetch(PDO::FETCH_ASSOC);
        if (!$resultArray) {
            return null;
        }

        if (!isset($resultArray[$column])) {
            $message = sprintf("Column '%s' not found in result set", $column);
            $this->logger?->error($message);
            throw new Exception($message);
        }

        return $resultArray[$column];
    }

    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }

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