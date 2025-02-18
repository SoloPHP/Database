<?php declare(strict_types=1);

namespace Solo\Database;

use PDO;
use PDOException;
use Solo\Logger;
use Exception;

final readonly class Connection
{
    private PDO $pdo;
    private string $prefix;
    private ?Logger $logger;
    private int $fetchMode;

    /**
     * @param Config $config Database configuration
     * @param Logger|null $logger Optional logger instance
     * @throws Exception When connection fails
     */
    public function __construct(Config $config, ?Logger $logger = null)
    {
        $this->prefix = $config->getPrefix();
        $this->logger = $logger;
        $this->fetchMode = $config->getFetchMode();

        try {
            $credentials = $config->getCredentials();
            $this->pdo = new PDO(
                $config->getDsn(),
                $credentials['username'],
                $credentials['password'],
                $config->getOptions()
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->logger?->error('Connection failed: ' . $e->getMessage());
            throw new Exception('Connection to the database failed: ' . $e->getMessage(), (int)$e->getCode());
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getLogger(): ?Logger
    {
        return $this->logger;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getFetchMode(): int
    {
        return $this->fetchMode;
    }
}