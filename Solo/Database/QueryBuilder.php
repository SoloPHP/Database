<?php

namespace Solo\Database;

use Solo\Logger;
use Exception;
use PDO;
use DateTimeImmutable;

/**
 * SQL query builder class for generating safe SQL queries with placeholders.
 *
 * Supports various types of placeholders:
 * - ?s - string (quoted)
 * - ?i - integer
 * - ?f - float
 * - ?a - array (for IN clauses)
 * - ?A - associative array (for SET clauses)
 * - ?t - table name (with prefix)
 * - ?p - raw parameter
 * - ?d - date (DateTimeImmutable)
 * - ?l - LIKE parameter (adds '%' for LIKE queries)
 */
readonly class QueryBuilder
{
    /**
     * Database date formats for different drivers
     *
     * Keys are PDO driver names, values are date format patterns
     */
    private const DATE_FORMATS = [
        'pgsql' => 'Y-m-d H:i:s.u P',
        'mysql' => 'Y-m-d H:i:s',
        'sqlite' => 'Y-m-d H:i:s',
        'sqlsrv' => 'Y-m-d H:i:s.u',
        'dblib' => 'Y-m-d H:i:s',
        'cubrid' => 'Y-m-d H:i:s',
    ];

    /**
     * Initialize query builder
     *
     * @param PDO $pdo PDO instance for database connection
     * @param string $prefix Optional table prefix for all queries
     * @param Logger|null $logger Optional logger instance for error logging
     */
    public function __construct(
        private PDO $pdo,
        private string $prefix = '',
        private ?Logger $logger = null
    ) {}

    /**
     * Prepare SQL query with placeholders
     *
     * @param string $sql SQL query with placeholders
     * @param mixed ...$params Parameters to replace placeholders
     * @return string Built SQL query
     * @throws Exception When placeholder count doesn't match parameters count
     */
    public function prepare(string $sql, ...$params): string
    {
        $pattern = '/\?([sifaAtpdl])/';
        $placeholderCount = preg_match_all($pattern, $sql);

        if ($placeholderCount !== count($params)) {
            $message = sprintf(
                "Mismatch between number of placeholders (%d) and provided parameters (%d)",
                $placeholderCount,
                count($params)
            );
            $this->logger?->error($message);
            throw new Exception($message);
        }

        if ($placeholderCount === 0) {
            return $sql;
        }

        $index = 0;
        return preg_replace_callback($pattern, function ($matches) use (&$index, $params) {
            return $this->replaceParameter($matches[1], $params[$index++]);
        }, $sql);
    }

    /**
     * Replace parameter placeholder with actual value
     *
     * @param string $type Placeholder type:
     *                     s - string (quoted)
     *                     i - integer
     *                     f - float
     *                     a - array (for IN clauses)
     *                     A - associative array (for SET clauses)
     *                     t - table name (with prefix)
     *                     p - raw parameter
     *                     d - date (DateTimeImmutable)
     *                     l - LIKE parameter (adds '%' for LIKE queries)
     * @param mixed $param Parameter value
     * @return string Replaced value
     * @throws Exception When parameter type is invalid
     */
    private function replaceParameter(string $type, mixed $param): string
    {
        return match ($type) {
            's' => $this->pdo->quote($param),
            'i' => (int)$param,
            'f' => is_float($param) ? $param : floatval(str_replace(',', '.', $param)),
            'a' => $this->handleArrayParameter($param),
            'A' => $this->handleAssociativeArrayParameter($param),
            't' => $this->handleTableParameter($param),
            'p' => $param,
            'd' => $this->handleDateParameter($param),
            'l' => $this->handleLikeParameter($param),
            default => $this->handleUnknownType($type),
        };
    }

    /**
     * Handle unknown placeholder type
     *
     * @param string $type Invalid placeholder type
     * @throws Exception Always throws to indicate invalid type
     * @return never
     */
    private function handleUnknownType(string $type): never
    {
        $message = sprintf("Unknown placeholder type: ?%s", $type);
        $this->logger?->error($message);
        throw new Exception($message);
    }

    /**
     * Handle array parameter for IN clauses
     *
     * @param mixed $param Expected to be array
     * @return string Comma-separated quoted values
     * @throws Exception When parameter is not array
     */
    private function handleArrayParameter(mixed $param): string
    {
        if (!is_array($param)) {
            $message = sprintf(
                "Expected array for ?a placeholder, %s given",
                gettype($param)
            );
            $this->logger?->error($message);
            throw new Exception($message);
        }
        return implode(', ', array_map([$this->pdo, 'quote'], $param));
    }

    /**
     * Handle associative array parameter for SET clauses
     *
     * @param mixed $param Expected to be associative array
     * @return string SET clause with key-value pairs
     * @throws Exception When parameter is not associative array
     */
    private function handleAssociativeArrayParameter(mixed $param): string
    {
        if (!is_array($param) || $param === array_values($param)) {
            $message = sprintf(
                "Expected associative array for ?A placeholder, %s given",
                gettype($param)
            );
            $this->logger?->error($message);
            throw new Exception($message);
        }

        $sets = [];
        foreach ($param as $key => $value) {
            $sets[] = $this->formatKeyValuePair($key, $value);
        }
        return implode(', ', $sets);
    }

    /**
     * Format key-value pair for SET clause
     *
     * @param string $key Column name
     * @param mixed $value Column value
     * @return string Formatted key-value pair
     */
    private function formatKeyValuePair(string $key, mixed $value): string
    {
        $escapedKey = "`" . str_replace("`", "``", $key) . "`";

        $escapedValue = match (true) {
            is_null($value) => 'NULL',
            is_int($value), is_float($value) => $value,
            is_bool($value) => (int)$value,
            ($value instanceof DateTimeImmutable) => $this->pdo->quote($value->format($this->getDateFormatForDatabase())),
            default => $this->pdo->quote($value),
        };

        return "$escapedKey = $escapedValue";
    }

    /**
     * Handle table name with prefix
     *
     * @param string $param Table name
     * @return string Prefixed and escaped table name
     */
    private function handleTableParameter(string $param): string
    {
        return '`' . (!empty($this->prefix) ? $this->prefix . '_' . $param : $param) . '`';
    }

    /**
     * Get date format for current database type
     *
     * @return string Date format pattern according to current PDO driver
     */
    private function getDateFormatForDatabase(): string
    {
        $dbType = strtolower($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        return self::DATE_FORMATS[$dbType] ?? self::DATE_FORMATS['mysql'];
    }

    /**
     * Handle date parameter formatting
     *
     * @param mixed $param Expected to be DateTimeImmutable
     * @return string Formatted date string according to database format
     * @throws Exception When parameter is not DateTimeImmutable
     */
    private function handleDateParameter(mixed $param): string
    {
        if (!$param instanceof DateTimeImmutable) {
            $message = sprintf(
                "Expected DateTimeImmutable for ?d placeholder, %s given",
                gettype($param)
            );
            $this->logger?->error($message);
            throw new Exception($message);
        }

        return $param->format($this->getDateFormatForDatabase());
    }

    /**
     * Handle LIKE parameter for LIKE queries
     *
     * @param mixed $param Parameter value
     * @return string Quoted value with '%' for LIKE
     */
    private function handleLikeParameter(mixed $param): string
    {
        $quotedParam = $this->pdo->quote($param);
        return "%" . trim($quotedParam, "'") . "%";
    }
}