<?php

namespace Solo\Database;

use PDO;

/**
 * Database configuration class
 */
class Config
{
    /** @var array<string, string> DSN patterns for different database types */
    private const DSN_PATTERNS = [
        'mysql' => 'mysql:host=%s;port=%d;dbname=%s',
        'dblib' => 'dblib:host=%s:%d;dbname=%s',
        'pgsql' => 'pgsql:host=%s;port=%d;dbname=%s',
        'mssql' => 'sqlsrv:Server=%s,%d;Database=%s',
        'cubrid' => 'cubrid:host=%s;port=%d;dbname=%s',
        'sqlite' => 'sqlite:%s'
    ];

    /**
     * @param string $hostname Database host
     * @param string $username Database username
     * @param string $password Database password
     * @param string $dbname Database name
     * @param string $type Database type (mysql, pgsql, etc.)
     * @param int $port Database port
     * @param string $prefix Table prefix
     * @param array<string, mixed> $options PDO options
     */
    public function __construct(
        private readonly string $hostname,
        private readonly string $username,
        private readonly string $password,
        private readonly string $dbname,
        private readonly string $type = 'mysql',
        private readonly int    $port = 3306,
        private readonly string $prefix = '',
        private array           $options = []
    )
    {
    }

    /**
     * Get database DSN string
     */
    public function getDsn(): string
    {
        return sprintf(self::DSN_PATTERNS[$this->type], $this->hostname, $this->port, $this->dbname);
    }

    /**
     * Get database credentials
     * @return array{username: string, password: string}
     */
    public function getCredentials(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password
        ];
    }

    /**
     * Get PDO options with defaults for MySQL
     * @return array<int, mixed>
     */
    public function getOptions(): array
    {
        if ($this->type === 'mysql') {
            $this->options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
            $this->options[PDO::ATTR_EMULATE_PREPARES] = true;
            $this->options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci";
        }
        return $this->options;
    }

    /**
     * Get table prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}