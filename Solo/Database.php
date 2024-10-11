<?php declare(strict_types=1);

namespace Solo;

use PDO;
use PDOStatement;

class Database
{
    protected PDO $pdo;
    protected PDOStatement $pdoStatement;

    private const DSN_PATTERNS = [
        'mysql' => 'mysql:host=%s;port=%d;dbname=%s',
        'dblib' => 'dblib:host=%s:%d;dbname=%s',
        'pgsql' => 'pgsql:host=%s;port=%d;dbname=%s',
        'mssql' => 'sqlsrv:Server=%s,%d;Database=%s',
        'cubrid' => 'cubrid:host=%s;port=%d;dbname=%s'
    ];

    private string $prefix = '';
    private string $logLocation = __DIR__ . '/logs';
    private bool $logErrors = true;

    public function connect(string $hostname, string $username, string $password, string $dbname, string $type = 'mysql', int $port = 3306, array $options = []): self
    {
        $dsn = sprintf(self::DSN_PATTERNS[$type], $hostname, $port, $dbname);
        if ($type === 'mysql') {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
            $options[PDO::ATTR_EMULATE_PREPARES] = true;
        }
        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            $this->error($e->getMessage());
        }
        return $this;
    }

    /**
     * @param $sql
     * @param ...$parameters
     * @return false|PDOStatement
     */
    public function query($sql, ...$parameters)
    {
        $query = $this->prepare($sql, ...$parameters);
        return $this->pdoStatement = $this->pdo->query($query);
    }

    public function prepare($query, ...$parameters): string
    {
        if (empty($parameters)) return $query;
        $parsed_query = '';
        $array = preg_split('~(\?[sifaAtp])~u', $query, 0, PREG_SPLIT_DELIM_CAPTURE);
        $parameters_num = count($parameters);
        $placeholders_num = floor(count($array) / 2);
        if ($placeholders_num != $parameters_num) {
            $this->error("Number of args ($parameters_num) doesn't match number of placeholders ($placeholders_num) in [$query]");
        }
        foreach ($array as $i => $part) {
            if (($i % 2) == 0) {
                $parsed_query .= $part;
                continue;
            }

            $value = array_shift($parameters);
            switch ($part) {
                case '?s':
                    $part = $this->pdo->quote($value);
                    break;
                case '?i':
                    $part = is_int($value) ? $value : (int)$value;
                    break;
                case '?f':
                    $part = is_float($value) ? $value : floatval(str_replace(',', '.', $value));
                    break;
                case '?a':
                    if (!is_array($value)) {
                        $this->error("?a placeholder expects array, " . gettype($value) . " given");
                    }
                    foreach ($value as &$v) {
                        $v = is_int($v) ? $v : $this->pdo->quote($v);
                    }
                    $part = implode(',', $value);
                    break;
                case '?A':
                    if (is_array($value) && $value !== array_values($value)) {
                        foreach ($value as $key => &$v) {
                            $v = '`' . $key . '`=' . (is_int($v) ? $v : (is_bool($v) ? (int)$v : $this->pdo->quote($v)));
                        }
                        $part = implode(', ', $value);
                    } else {
                        $this->error("?A placeholder expects Associative array, " . gettype($value) . " given");
                    }
                    break;
                case '?t':
                    $part = '`' . $value . '`';
                    break;
                case '?p':
                    $part = $value;
                    break;
            }
            $parsed_query .= $part;
        }
        return $parsed_query;
    }

    /** Fetches all rows from a result set and return as array of objects.
     * If $primaryKey return associative array of objects
     * @return array|false
     */
    public function results($primaryKey = '')
    {
        $results = $this->pdoStatement->fetchAll($this->pdo::FETCH_CLASS);
        if (!empty($primaryKey)) {
            $associativeResults = array();
            foreach ($results as $row) {
                $associativeResults[$row->$primaryKey] = $row;
            }
            return $associativeResults;
        } else {
            return $results;
        }
    }

    /** Fetches one row and returns it as an object
     * If $column is given, returns a single column of a result set
     */
    public function result($column = null)
    {
        if ($column) {
            $data = $this->pdoStatement->fetch();
            if (isset($data[$column])) {
                return $data[$column];
            } else {
                $this->error("$column is not present in result set");
            }
        } else {
            return $this->pdoStatement->fetchObject();
        }
    }

    /**
     * @return string|false
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /** Affected rows */
    public function rowCount(): int
    {
        return $this->pdoStatement->rowCount();
    }

    public function setPrefix($prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setLogLocation(string $location): self
    {
        $this->logLocation = $location;
        $this->ensureLogLocationExists();
        return $this;
    }

    public function setLogErrors(bool $logErrors): self
    {
        $this->logErrors = $logErrors;
        return $this;
    }

    private function ensureLogLocationExists(): void
    {
        if (!is_dir($this->logLocation)) {
            mkdir($this->logLocation, 0777, true);
            file_put_contents($this->logLocation . '/.htaccess', "order deny,allow\ndeny from all");
        }
    }

    /**
     * @throws \Exception
     */
    private function error(string $message): void
    {
        if ($this->logErrors) {
            $logString = sprintf("[%s] Error: %s%s----------------------%s", date('d/m/Y H:i:s'), $message, PHP_EOL, PHP_EOL);
            file_put_contents($this->logLocation . '/sql.txt', $logString, FILE_APPEND);
        }
        throw new \Exception($message);
    }
}