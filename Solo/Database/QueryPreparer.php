<?php declare(strict_types=1);

namespace Solo\Database;

use Solo\Database\Interfaces\QueryPreparerInterface;
use Solo\Logger;
use Exception;
use PDO;
use DateTimeImmutable;

final class QueryPreparer implements QueryPreparerInterface
{
    private const DATE_FORMATS = [
        'pgsql' => 'Y-m-d H:i:s.u P',
        'mysql' => 'Y-m-d H:i:s',
        'sqlite' => 'Y-m-d H:i:s',
        'sqlsrv' => 'Y-m-d H:i:s.u',
        'dblib' => 'Y-m-d H:i:s',
        'cubrid' => 'Y-m-d H:i:s',
    ];

    public function __construct(
        private PDO     $pdo,
        private string  $prefix = '',
        private ?Logger $logger = null
    )
    {
    }

    public function prepare(string $sql, ...$params): string
    {
        $pattern = '/\?([sifaAtcldMr])/';
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
     *                     c - column name
     *                     l - LIKE parameter (adds '%' for LIKE queries)
     *                     d - date (DateTimeImmutable)
     *                     r - raw parameter
     * @param mixed $param Parameter value
     * @return mixed Replaced value
     * @throws Exception When parameter type is invalid
     */
    private function replaceParameter(string $type, mixed $param): mixed
    {
        return match ($type) {
            's' => $this->pdo->quote($param),
            'i' => (int)$param,
            'f' => is_float($param) ? $param : floatval(str_replace(',', '.', (string)$param)),
            'a' => $this->handleArrayParameter($param),
            'A' => $this->handleAssociativeArrayParameter($param),
            't' => $this->handleTableParameter($param),
            'c' => $this->handleColumnEscape($param),
            'l' => $this->handleLikeParameter($param),
            'd' => $this->handleDateParameter($param),
            'M' => $this->handleMultiRowParameter($param),
            'r' => $param,
            default => $this->handleUnknownType($type),
        };
    }

    private function handleUnknownType(string $type): never
    {
        $message = sprintf("Unknown placeholder type: ?%s", $type);
        $this->logger?->error($message);
        throw new Exception($message);
    }

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

        $quotedItems = array_map([$this->pdo, 'quote'], $param);
        return '(' . implode(', ', $quotedItems) . ')';
    }

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

    private function formatKeyValuePair(string $key, mixed $value): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
            throw new Exception("Invalid column name in SET clause: {$key}");
        }

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

    private function handleTableParameter(string $param): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $param)) {
            throw new Exception("Invalid table name: {$param}");
        }

        return '`' . (!empty($this->prefix) ? $this->prefix . '_' . $param : $param) . '`';
    }

    private function handleColumnEscape(string $column): string
    {
        if (!preg_match('/^[a-zA-Z0-9_`]+$/', $column)) {
            $message = sprintf("Invalid column name for ?c placeholder: %s", $column);
            $this->logger?->error($message);
            throw new Exception($message);
        }

        $escaped = str_replace('`', '``', $column);

        return "`{$escaped}`";
    }

    private function handleLikeParameter(mixed $param): string
    {
        $escapedParam = str_replace(['%', '_'], ['\%', '\_'], (string)$param);
        return $this->pdo->quote("%{$escapedParam}%");
    }

    private function handleDateParameter(mixed $param): string
    {
        if ($param === null) {
            return 'NULL';
        }

        if (!$param instanceof DateTimeImmutable) {
            $message = sprintf(
                "Expected DateTimeImmutable or null for ?d placeholder, %s given",
                gettype($param)
            );
            $this->logger?->error($message);
            throw new Exception($message);
        }

        return $this->pdo->quote(
            $param->format($this->getDateFormatForDatabase())
        );
    }

    private function getDateFormatForDatabase(): string
    {
        $dbType = strtolower($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        return self::DATE_FORMATS[$dbType] ?? self::DATE_FORMATS['mysql'];
    }

    private function handleMultiRowParameter(mixed $param): string
    {
        if (!is_array($param)) {
            $message = sprintf(
                "Expected array of arrays for ?M placeholder, %s given",
                gettype($param)
            );
            $this->logger?->error($message);
            throw new Exception($message);
        }

        $rows = [];
        foreach ($param as $row) {
            if (!is_array($row)) {
                $message = sprintf(
                    "Each element in ?M placeholder must be an array, %s given",
                    gettype($row)
                );
                $this->logger?->error($message);
                throw new Exception($message);
            }
            $quotedFields = array_map([$this->pdo, 'quote'], $row);
            $rows[] = '(' . implode(', ', $quotedFields) . ')';
        }

        return implode(', ', $rows);
    }
}