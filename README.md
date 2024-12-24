# Solo Database

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

// Fetch all results as array of objects
$users = $db->query("SELECT * FROM ?t", 'users')->fetchAll();

// Fetch all results with primary key as array key
$users = $db->query("SELECT * FROM ?t", 'users')->fetchAll('id');

// Fetch single row as object
$user = $db->query("SELECT * FROM ?t WHERE id = ?i", 'users', 1)->fetchObject();

// Fetch single row as associative array
$user = $db->query("SELECT * FROM ?t WHERE id = ?i", 'users', 1)->fetchAssoc();

// Fetch single column value
$name = $db->query("SELECT name FROM ?t WHERE id = ?i", 'users', 1)->fetchObject('name');

// SELECT with LIKE clause
$searchTerm = '%john%';
$users = $db->query("SELECT * FROM ?t WHERE name LIKE ?l", 'users', $searchTerm)->fetchAll();

// Prepare a query without executing it
$sql = $db->prepare("SELECT * FROM ?t WHERE user_id = ?i AND status = ?s", 
    'orders',
    15,
    'pending'
);
// Result: SELECT * FROM prefix_orders WHERE user_id = 15 AND status = 'pending'

// Useful for debugging or when you need to see the final query
echo $sql;

// Can still execute the prepared query later
$db->query($sql)->fetchAll();
```

## Query Placeholders

- `?s` - String (safely quoted)
- `?i` - Integer
- `?f` - Float
- `?a` - Array (for IN statements)
- `?A` - Associative Array (for SET statements)
- `?t` - Table name (with prefix)
- `?p` - Raw parameter (unescaped)
- `?d` - Date (expects DateTimeImmutable, formats according to database type)
- `?l` - Like condition (for LIKE statements with wildcards)

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

- `fetchAll()`: Returns `array<int|string, stdClass>`
- `fetchAssoc()`: Returns `array<string, mixed>|string|int|float|bool|null`
- `fetchObject()`: Returns `stdClass|string|int|float|bool|null`
- `rowCount()`: Returns `int`
- `lastInsertId()`: Returns `string|false`

## Error Handling

All database operations are wrapped in try-catch blocks and will throw exceptions with detailed error messages when queries fail. When a logger is configured, all errors are automatically logged with relevant context information.

## License

MIT License. See LICENSE file for details.