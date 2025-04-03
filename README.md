# Solo Database
[![Version](https://img.shields.io/badge/version-2.10.0-blue.svg)](https://github.com/solophp/database)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](https://opensource.org/licenses/MIT)

Lightweight and flexible PHP database wrapper with support for multiple database types, query building, and optional logging.

## Installation
```bash
composer require solophp/database
```

## Features
- Support for MySQL, PostgreSQL, SQLite, SQL Server, and other PDO-compatible databases
- Safe query building with type-specific placeholders
- Flexible null value support, including for date parameters
- Configurable fetch modes (arrays or objects)
- Query preparation without execution
- Optional table prefixing
- Integration with PSR-3 compatible Solo Logger
- Transaction support
- Clean and flexible API with method chaining

## Requirements
- PHP 8.2+
- PDO extension
- Solo Logger ^1.0

## API Reference

| Method | Arguments | Description | Return Type |
|--------|-----------|-------------|-------------|
| `query()` | `string $sql, mixed ...$params` | Execute a query with placeholders | `self` |
| `prepare()` | `string $sql, mixed ...$params` | Prepare SQL string without execution | `string` |
| `fetchAll()` | `?int $fetchMode = null` | Fetch all rows | `array<int|string, array|stdClass>` |
| `fetch()` | `?int $fetchMode = null` | Fetch single row | `array|stdClass|null` |
| `fetchColumn()` | `int $columnIndex = 0` | Fetch single column from next row | `mixed` |
| `lastInsertId()` | — | Get last inserted ID | `string|false` |
| `rowCount()` | — | Get number of affected rows | `int` |
| `beginTransaction()` | — | Begin transaction | `void` |
| `commit()` | — | Commit transaction | `void` |
| `rollBack()` | — | Roll back transaction | `void` |
| `inTransaction()` | — | Check if in transaction | `bool` |
| `withTransaction()` | `callable $callback` | Run logic inside a safe transaction (auto rollback on exception) | `mixed` |

## Query Placeholders

| Placeholder | Description |
|-------------|-------------|
| `?s` | String (safely quoted) |
| `?i` | Integer |
| `?f` | Float |
| `?a` | Array (for IN statements) |
| `?A` | Associative Array (for SET statements) |
| `?t` | Table name (with prefix) |
| `?c` | Column name (safely quoted) |
| `?d` | Date (DateTimeImmutable or null) |
| `?l` | LIKE condition with wildcards |
| `?M` | Multi-row INSERT (array of arrays) |
| `?r` | Raw parameter (unescaped) |

## Usage

### Basic Configuration

```php
use Solo\Database\{Config, Connection};
use Solo\Logger;
use PDO;

$config = new Config(
    hostname: 'localhost',
    username: 'user',
    password: 'pass',
    dbname: 'mydb',
    prefix: 'prefix_',
    fetchMode: PDO::FETCH_OBJ
);

$logger = new Logger('/path/to/logs/db.log');
$connection = new Connection($config, $logger);
$db = new Database($connection);
```

### Query Examples

```php
// Basic SELECT
$users = $db->query("SELECT * FROM ?t", 'users')->fetchAll();

// INSERT with associative array
$userData = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
    'created_at' => new DateTimeImmutable()
];
$db->query("INSERT INTO ?t SET ?A", 'users', $userData);

// INSERT multiple rows
$data = [['John', 30], ['Alice', 25]];
$db->query("INSERT INTO ?t (name, age) VALUES ?M", 'users', $data);

// Handling null values
$userData = [
    'name' => 'Jane Doe',
    'created_at' => new DateTimeImmutable(),
    'updated_at' => null,
];
$db->query("INSERT INTO ?t SET ?A", 'users', $userData);

// Fetch single row
$user = $db->query("SELECT * FROM ?t WHERE id = ?i", 'users', 1)->fetch();

// Override fetch mode
$userArray = $db->query("SELECT * FROM ?t WHERE id = ?i", 'users', 1)->fetch(PDO::FETCH_ASSOC);

// IN clause
$ids = [1, 2, 3];
$result = $db->query("SELECT * FROM ?t WHERE id IN ?a", 'users', $ids)->fetchAll();

// Dynamic column
$column = 'email';
$userEmail = $db->query("SELECT ?c FROM ?t WHERE id = ?i", $column, 'users', 1)->fetchColumn();

// Transaction (classic)
try {
    $db->beginTransaction();
    
    $db->query("INSERT INTO ?t SET ?A", 'orders', ['product' => 'Laptop']);
    $db->query("UPDATE ?t SET balance = balance - ?f WHERE id = ?i", 'accounts', 799.99, 1);
    
    $db->commit();
} catch (Exception $e) {
    if ($db->inTransaction()) {
    $db->rollBack();
    }
    throw $e;
}

// Transaction (preferred)
$db->withTransaction(function () use ($db) {
    $db->query("INSERT INTO ?t SET ?A", 'orders', ['product' => 'Laptop']);
    $db->query("UPDATE ?t SET balance = balance - ?f WHERE id = ?i", 'accounts', 799.99, 1);
});

// Prepare only
$sql = $db->prepare("SELECT * FROM ?t WHERE user_id = ?i AND status = ?s", 'orders', 15, 'pending');

// Fetch column
$email = $db->query("SELECT email FROM ?t WHERE id = ?i", 'users', 1)->fetchColumn();

// Raw expressions
use Solo\Database\Expressions\RawExpression;
$db->query("UPDATE ?t SET ?A WHERE id = ?i", 'orders', [
    'number' => new RawExpression("CONCAT(RIGHT(phone, 4), '-', id)")
], 42);
```

## Database Support

Date formatting is handled automatically per driver:

- PostgreSQL: `Y-m-d H:i:s.u P`
- MySQL: `Y-m-d H:i:s`
- SQLite: `Y-m-d H:i:s`
- SQL Server: `Y-m-d H:i:s.u`
- DBLIB: `Y-m-d H:i:s`
- CUBRID: `Y-m-d H:i:s`

## Return Types

| Method | Description | Return Type |
|--------|-------------|-------------|
| `fetchAll(?int $fetchMode = null)` | Get all rows | `array<int|string, array|stdClass>` |
| `fetch(?int $fetchMode = null)` | Get one row | `array|stdClass|null` |
| `fetchColumn(int $columnIndex = 0)` | Get column from next row | `mixed` |
| `rowCount()` | Affected rows from last query | `int` |
| `lastInsertId()` | Last inserted auto-increment ID | `string|false` |

## Error Handling
- All database operations are wrapped in try-catch blocks
- Exceptions are thrown with meaningful messages
- Logging is automatic when PSR-3 logger is configured
- Transactions via `withTransaction()` auto-rollback on failure

## License
MIT License. See LICENSE file for details.