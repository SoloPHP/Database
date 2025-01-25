<?php declare(strict_types=1);

namespace Solo\Database;

use PDO;
use PDOException;
use Solo\Logger;
use Exception;

/**
 * Database connection class
 */
final readonly class Connection
{
    private PDO $pdo;
    private string $prefix;
    private ?Logger $logger;

    /**
     * Initialize database connection
     *
     * @param Config $config Database configuration
     * @param Logger|null $logger Optional logger instance
     * @throws Exception When connection fails
     */
    public function __construct(Config $config, ?Logger $logger = null)
    {
        $this->prefix = $config->getPrefix();
        $this->logger = $logger;

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

    /**
     * Get PDO instance
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Get logger instance
     */
    public function getLogger(): ?Logger
    {
        return $this->logger;
    }

    /**
     * Get table prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}