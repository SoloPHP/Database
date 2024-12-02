# Solo Database

Lightweight and flexible PHP database wrapper with support for multiple database types, query building, and optional logging.

## Installation

```bash
composer require solophp/database
```

## Features

- Support for MySQL, PostgreSQL, SQLite, and other databases
- Safe query building with type-specific placeholders
- Optional table prefixing
- Integration with PSR-3 compatible Solo Logger
- Clean and flexible API

## Requirements

- PHP 8.1+
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
    'age' => 25
];
$db->query("INSERT INTO ?t SET ?A", 'users', $userData);

// SELECT with IN clause
$ids = [1, 2, 3];
$db->query("SELECT * FROM ?t WHERE id IN (?a)", 'users', $ids);
$users = $db->results();

// Fetch all results as array of objects
$users = $db->query("SELECT * FROM ?t", 'users')->results();

// Fetch all results with primary key as array key
$users = $db->query("SELECT * FROM ?t", 'users')->results('id');

// Fetch single row as object
$user = $db->query("SELECT * FROM ?t WHERE id = ?i", 'users', 1)->result();

// Fetch single column value
$name = $db->query("SELECT name FROM ?t WHERE id = ?i", 'users', 1)->result('name');
```

## Query Placeholders

- `?s` - String (safely quoted)
- `?i` - Integer
- `?f` - Float
- `?a` - Array (for IN statements)
- `?A` - Associative Array (for SET statements)
- `?t` - Table name (with prefix)
- `?p` - Raw parameter

## License

MIT License. See LICENSE file for details.