<?php declare(strict_types=1);

namespace Solo;

use PDO;
use PDOStatement;
use PDOException;
use Exception;

class Database
{
    protected PDO $pdo;
    protected ?PDOStatement $stmt = null;

    private const DSN_PATTERNS = [
        'mysql' => 'mysql:host=%s;port=%d;dbname=%s',
        'dblib' => 'dblib:host=%s:%d;dbname=%s',
        'pgsql' => 'pgsql:host=%s;port=%d;dbname=%s',
        'mssql' => 'sqlsrv:Server=%s,%d;Database=%s',
        'cubrid' => 'cubrid:host=%s;port=%d;dbname=%s',
        'sqlite' => 'sqlite:%s'
    ];

    private string $prefix = '';
    private string $logLocation = __DIR__ . '/logs';
    private bool $logErrors = true;

    /**
     * Establishes a connection to the database.
     *
     * @param string $hostname The database host.
     * @param string $username The username for the database.
     * @param string $password The password for the database.
     * @param string $dbname The name of the database.
     * @param string $type The database type (e.g., mysql, pgsql).
     * @param int $port The port for the database connection.
     * @param array $options Additional PDO options.
     * @return self
     * @throws Exception If the connection fails.
     */
    public function connect(
        string $hostname,
        string $username,
        string $password,
        string $dbname,
        string $type = 'mysql',
        int $port = 3306,
        array $options = []
    ): self {
        $dsn = sprintf(self::DSN_PATTERNS[$type], $hostname, $port, $dbname);

        if ($type === 'mysql') {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
            $options[PDO::ATTR_EMULATE_PREPARES] = true;
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci";
        }

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->handleError('Connection to the database failed: ' . $e->getMessage(), (int)$e->getCode());
        }
        return $this;
    }

    /**
     * Sets the table prefix for queries.
     *
     * @param string $prefix The table prefix to set.
     * @return self Returns the current instance for method chaining.
     */
    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Gets the current table prefix.
     *
     * @return string The current table prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Sets the log location.
     *
     * @param string $logLocation The directory path to store logs.
     * @return self Returns the current instance for method chaining.
     */
    public function setLogLocation(string $logLocation): self
    {
        $this->logLocation = rtrim($logLocation, '/');
        return $this;
    }

    /**
     * Enables or disables error logging.
     *
     * @param bool $logErrors Whether to enable error logging.
     * @return self Returns the current instance for method chaining.
     */
    public function setLogErrors(bool $logErrors): self
    {
        $this->logErrors = $logErrors;
        return $this;
    }

    /**
     * Handles the error based on the logErrors setting.
     *
     * @param string $message The error message.
     * @param int $code The error code.
     * @throws Exception If logErrors is disabled.
     */
    private function handleError(string $message, int $code = 0): void
    {
        if ($this->logErrors) {
            $this->logError($message);
        } else {
            throw new Exception($message, $code);
        }
    }

    /**
     * Logs the error message if logging is enabled.
     *
     * @param string $message The error message to log.
     */
    private function logError(string $message): void
    {
        if (!file_exists($this->logLocation)) {
            mkdir($this->logLocation, 0777, true);
        }

        $logFile = $this->logLocation . '/db_errors.log';

        $maxFileSize = 1024 * 1024; // 1MB

        if (file_exists($logFile) && filesize($logFile) >= $maxFileSize) {
            $this->rotateLog($logFile);
        }

        $date = date('Y-m-d H:i:s');
        $formattedMessage = "[$date] - $message" . PHP_EOL;

        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }

    /**
     * Performs log rotation.
     *
     * @param string $logFile The log file to rotate.
     */
    private function rotateLog(string $logFile): void
    {
        $rotatedLogFile = $logFile . '.' . date('Y-m-d_H-i-s');

        rename($logFile, $rotatedLogFile);

        touch($logFile);
    }

    /**
     * Prepares and executes an SQL query.
     *
     * @param string $sql The SQL query.
     * @param mixed ...$params The parameters to bind to the query.
     * @return self Returns the current instance for method chaining.
     * @throws Exception If the query execution fails.
     */
    public function query(string $sql, ...$params): self
    {
        try {
            $parsedSql = $this->build($sql, ...$params);
            $this->stmt = $this->pdo->prepare($parsedSql);
            $this->stmt->execute();
        } catch (PDOException $e) {
            $this->handleError('Query failed: ' . $e->getMessage() . ' | SQL: ' . $sql, (int)$e->getCode());
        }
        return $this;
    }

    /**
     * Builds the SQL query by replacing placeholders with parameters.
     *
     * @param string $sql The SQL query containing placeholders.
     * @param mixed ...$params The parameters to bind to the placeholders.
     * @return string The SQL query with bound parameters.
     * @throws Exception If an unknown placeholder type is encountered or if there's a mismatch in placeholder count.
     */
    public function build(string $sql, ...$params): string
    {
        $pattern = '/\?(s|i|f|a|A|t|p)/';

        $placeholderCount = preg_match_all($pattern, $sql);

        if ($placeholderCount !== count($params)) {
            $this->handleError("Mismatch between number of placeholders ($placeholderCount) and provided parameters (" . count($params) . ")");
        }

        if ($placeholderCount === 0) {
            return $sql;
        }

        $index = 0;

        return preg_replace_callback($pattern, function ($matches) use (&$index, $params) {
            $param = $params[$index++];
            $type = $matches[1];

            switch ($type) {
                case 's':
                    return $this->pdo->quote($param);
                case 'i':
                    return (int)$param;
                case 'f':
                    return is_float($param) ? $param : floatval(str_replace(',', '.', $param));
                case 'a':
                    if (!is_array($param)) {
                        $this->handleError("Expected array for ?a placeholder, " . gettype($param) . " given");
                    }
                    return implode(', ', array_map([$this->pdo, 'quote'], $param));
                case 'A':
                    if (!is_array($param) || $param === array_values($param)) {
                        $this->handleError("Expected associative array for ?A placeholder, " . gettype($param) . " given");
                    }
                    $sets = [];
                    foreach ($param as $key => $value) {
                        $sets[] = "`" . str_replace("`", "``", $key) . "` = " .
                            (is_null($value) ? 'NULL' :
                                (is_int($value) ? $value :
                                    (is_bool($value) ? (int)$value :
                                        $this->pdo->quote($value)
                                    )
                                )
                            );
                    }
                    return implode(', ', $sets);
                case 't':
                    return '`' . (!empty($this->prefix) ? $this->prefix . '_' . $param : $param) . '`';
                case 'p':
                    return $param;
                default:
                    $this->handleError("Unknown placeholder type: ?$type");
            }
        }, $sql);
    }

    /**
     * Fetches all rows from a result set and returns them as an array of objects.
     * If a primary key is provided, returns an associative array of objects keyed by that primary key.
     *
     * @param string $primaryKey The column name to use as the associative array key (optional).
     * @return array|false An array of objects or false if no results are found.
     */
    public function results(string $primaryKey = '')
    {
        $results = $this->stmt->fetchAll($this->pdo::FETCH_CLASS);

        if (empty($primaryKey)) {
            return $results;
        }

        $associativeResults = [];
        foreach ($results as $row) {
            $associativeResults[$row->$primaryKey] = $row;
        }

        return $associativeResults;
    }

    /**
     * Fetches one row from the result set as an object.
     * If a column name is given, returns the value of that column instead.
     *
     * @param string|null $column The name of the column to fetch (optional).
     * @return array|object|false The fetched column value or an object.
     * @throws Exception If the column is not present in the result set.
     */
    public function result(?string $column = null)
    {
        if (!$column) {
            return $this->stmt->fetchObject();
        }

        $resultArray = $this->stmt->fetch(PDO::FETCH_ASSOC);
        if (!array_key_exists($column, $resultArray)) {
            $this->handleError("Column '$column' not found in result set.");
        }

        return $resultArray[$column];
    }

    /**
     * Returns the ID of the last inserted row.
     *
     * @return string|false The last inserted ID or false on failure.
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Returns the number of rows affected by the last SQL statement.
     *
     * @return int The number of affected rows.
     */
    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    /**
     * Cleans up resources used by the statement.
     */
    public function __destruct()
    {
        $this->stmt = null;
    }
}