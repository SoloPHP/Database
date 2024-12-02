<?php

namespace Solo\Database;

use Solo\Logger;
use Exception;
use PDO;

/**
 * SQL query builder class
 */
class QueryBuilder
{
    /**
     * Initialize query builder
     *
     * @param PDO $pdo PDO instance
     * @param string $prefix Table prefix
     * @param Logger|null $logger Optional logger instance
     */
    public function __construct(
        private PDO $pdo,
        private string $prefix = '',
        private ?Logger $logger = null
    ) {}

    /**
     * Build SQL query with placeholders
     *
     * @param string $sql SQL query with placeholders
     * @param mixed ...$params Parameters to replace placeholders
     * @throws Exception When placeholder count doesn't match parameters count
     * @return string Built SQL query
     */
    public function build(string $sql, ...$params): string
    {
        $pattern = '/\?([sifaAtp])/';
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
     * @param string $type Placeholder type (s|i|f|a|A|t|p)
     * @param mixed $param Parameter value
     * @throws Exception When parameter type is invalid
     * @return string Replaced value
     */
    private function replaceParameter(string $type, $param): string
    {
        return match ($type) {
            's' => $this->pdo->quote($param),
            'i' => (int)$param,
            'f' => is_float($param) ? $param : floatval(str_replace(',', '.', $param)),
            'a' => $this->handleArrayParameter($param),
            'A' => $this->handleAssociativeArrayParameter($param),
            't' => $this->handleTableParameter($param),
            'p' => $param,
            default => $this->handleUnknownType($type),
        };
    }

    /**
     * Handle unknown placeholder type
     *
     * @param string $type Invalid placeholder type
     * @throws Exception Always
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
     * @throws Exception When parameter is not array
     * @return string Comma-separated quoted values
     */
    private function handleArrayParameter($param): string
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
     * @throws Exception When parameter is not associative array
     * @return string SET clause
     */
    private function handleAssociativeArrayParameter($param): string
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
    private function formatKeyValuePair(string $key, $value): string
    {
        $escapedKey = "`" . str_replace("`", "``", $key) . "`";

        $escapedValue = match (true) {
            is_null($value) => 'NULL',
            is_int($value), is_float($value) => $value,
            is_bool($value) => (int)$value,
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
}