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

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->getPdo();
        $this->logger = $connection->getLogger();
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

    public function fetchAll(): array
    {
        return $this->stmt->fetchAll($this->pdo::FETCH_ASSOC) ?: [];
    }

    public function fetch(): ?array
    {
        return $this->stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function fetchObject(): ?stdClass
    {
        return $this->stmt->fetchObject();
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