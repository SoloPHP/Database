<?php declare(strict_types=1);

namespace Solo;

use PDO;
use PDOStatement;
use PDOException;
use Exception;
use Solo\Database\Connection;
use Solo\Database\Interfaces\DatabaseInterface;
use Solo\Database\QueryPreparer;
use stdClass;

final class Database implements DatabaseInterface
{
    private PDO $pdo;
    private ?PDOStatement $stmt = null;
    private QueryPreparer $queryPreparer;
    private ?Logger $logger;
    private int $defaultFetchMode;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->getPdo();
        $this->logger = $connection->getLogger();
        $this->defaultFetchMode = $connection->getFetchMode();
        $this->queryPreparer = new QueryPreparer(
            $this->pdo,
            $connection->getPrefix(),
            $this->logger
        );
    }

    public function query(string $sql, mixed ...$params): self
    {
        try {
            $parsedSql = $this->queryPreparer->prepare($sql, ...$params);
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
        return $this->queryPreparer->prepare($sql, ...$params);
    }

    public function fetchAll(?int $fetchMode = null): array
    {
        $mode = $fetchMode ?? $this->defaultFetchMode;
        return $this->stmt->fetchAll($mode) ?: [];
    }

    public function fetch(?int $fetchMode = null): array|stdClass|null
    {
        $mode = $fetchMode ?? $this->defaultFetchMode;
        return $this->stmt->fetch($mode) ?: null;
    }

    public function fetchColumn(int $columnIndex = 0): mixed
    {
        return $this->stmt->fetchColumn($columnIndex);
    }

    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }

    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function __destruct()
    {
        $this->stmt = null;
    }
}