<?php declare(strict_types=1);

namespace Solo\Database;

use PDO;

final class Config
{
    private const DSN_PATTERNS = [
        'mysql' => 'mysql:host=%s;port=%d;dbname=%s',
        'dblib' => 'dblib:host=%s:%d;dbname=%s',
        'pgsql' => 'pgsql:host=%s;port=%d;dbname=%s',
        'mssql' => 'sqlsrv:Server=%s,%d;Database=%s',
        'cubrid' => 'cubrid:host=%s;port=%d;dbname=%s',
        'sqlite' => 'sqlite:%s'
    ];

    public function __construct(
        private readonly string $hostname,
        private readonly string $username,
        private readonly string $password,
        private readonly string $dbname,
        private readonly string $type = 'mysql',
        private readonly int    $port = 3306,
        private readonly string $prefix = '',
        private readonly int    $fetchMode = PDO::FETCH_ASSOC,
        private array           $options = []
    )
    {
    }

    public function getDsn(): string
    {
        return sprintf(self::DSN_PATTERNS[$this->type], $this->hostname, $this->port, $this->dbname);
    }

    public function getCredentials(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password
        ];
    }

    public function getOptions(): array
    {
        $this->options[PDO::ATTR_DEFAULT_FETCH_MODE] = $this->fetchMode;

        if ($this->type === 'mysql') {
            $this->options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
            $this->options[PDO::ATTR_EMULATE_PREPARES] = true;
            $this->options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci";
        }
        return $this->options;
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