# Solo Database

[![Version](https://img.shields.io/badge/version-2.6.0-blue.svg)](https://github.com/solophp/database)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](https://opensource.org/licenses/MIT)

Lightweight and flexible PHP database wrapper with support for multiple database types, query building, and optional logging.

## Installation

```bash
composer require solophp/database
```

## Features

- Support for MySQL, PostgreSQL, SQLite, SQL Server, and other PDO-compatible databases
- Safe query building with type-specific placeholders
- Query preparation without execution
- Optional table prefixing
- Integration with PSR-3 compatible Solo Logger
- Transaction support
- Clean and flexible API with method chaining

## Requirements

- PHP 8.2+
- PDO extension
- Solo Logger ^1.0

## Usage

```php
use Solo\Database\{Config, Connection};
use Solo\Logger;

// Configure database connection
$config = new Config(
    hostname: 'localhost',
    username: 'user',
    password: 'pass',
    dbname: 'mydb',
    prefix: 'prefix_'
);

// Create connection with optional logging
$logger = new Logger('/path/to/logs/db.log');
$connection = new Connection($config, $logger);
$db = new Database($connection);

// INSERT with associative array
$userData = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
    'created_at' => new DateTimeImmutable()
];
$db->query("INSERT INTO ?t SET ?A", 'users', $userData);

// SELECT with IN clause
$ids = [1, 2, 3];
$db->query("SELECT * FROM ?t WHERE id IN (?a)", 'users', $ids);
$users = $db->fetchAll();

// Fetch all results as array of arrays
$users = $db->query("SELECT * FROM ?t", 'users')->fetchAll();

// Fetch single row as array
$user = $db->query("SELECT * FROM ?t WHERE id = ?i", 'users', 1)->fetch();

// Fetch single row as object
$user = $db->query("SELECT * FROM ?t WHERE id = ?i", 'users', 1)->fetchObject();

// SELECT with dynamic column name
$column = 'email';
$users = $db->query("SELECT ?c FROM ?t WHERE id = ?i", $column, 'users', 1)->fetch();

// Transaction example
try {
    $db->beginTransaction();

    $db->query("INSERT INTO ?t SET ?A", 'orders', $orderData);
    $db->query("UPDATE ?t SET balance = balance - ?f WHERE id = ?i", 'accounts', $amount, $userId);
    
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}

// Prepare a query without executing it
$sql = $db->prepare("SELECT * FROM ?t WHERE user_id = ?i AND status = ?s", 
    'orders',
    15,
    'pending'
);
// Result: SELECT * FROM prefix_orders WHERE user_id = 15 AND status = 'pending'
```

## Query Placeholders

- `?s` - String (safely quoted)
- `?i` - Integer
- `?f` - Float
- `?a` - Array (for IN statements)
- `?A` - Associative Array (for SET statements)
- `?t` - Table name (with prefix)
- `?c` - Column name (safely quoted)
- `?p` - Raw parameter (unescaped)
- `?d` - Date (expects DateTimeImmutable, formats according to database type)
- `?l` - Like condition

## Database Support

The library automatically handles date formatting for different database types:

- PostgreSQL: `Y-m-d H:i:s.u P`
- MySQL: `Y-m-d H:i:s`
- SQLite: `Y-m-d H:i:s`
- SQL Server: `Y-m-d H:i:s.u`
- DBLIB: `Y-m-d H:i:s`
- CUBRID: `Y-m-d H:i:s`

## Return Types

The library provides type-safe return values:

- `fetchAll()`: Returns `array<string, mixed>[]`
- `fetch()`: Returns `array<string, mixed>|null`
- `fetchObject()`: Returns `stdClass|null`
- `rowCount()`: Returns `int`
- `lastInsertId()`: Returns `string|false`

## Error Handling

All database operations are wrapped in try-catch blocks and will throw exceptions with detailed error messages when queries fail. When a logger is configured, all errors are automatically logged with relevant context information.

## License

MIT License. See LICENSE file for details.
```