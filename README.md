# Solo Database Class

A lightweight, secure, and flexible PHP database wrapper class that provides an elegant interface for database operations with built-in error handling and logging capabilities.

## Features

- Support for multiple database types (MySQL, PostgreSQL, MSSQL, SQLite, CUBRID)
- Secure parameter binding with type-specific placeholders
- Built-in error logging with rotation
- Table prefix support
- Method chaining
- Strict type checking
- UTF-8 support for MySQL
- Flexible result fetching
- PDO-based for security and compatibility

## Installation

```bash
composer require solo/database
```

## Usage

### Basic Connection

```php
use Solo\Database;

$db = new Database();
$db->connect(
    hostname: 'localhost',
    username: 'user',
    password: 'password',
    dbname: 'mydatabase',
    type: 'mysql',    // Optional, defaults to 'mysql'
    port: 3306        // Optional, defaults to 3306
);
```

### Query Execution with Type-Safe Placeholders

The class supports various placeholder types for safe parameter binding:

- `?s` - String values
- `?i` - Integer values
- `?f` - Float values
- `?a` - Array values (for IN clauses)
- `?A` - Associative array (for SET clauses)
- `?t` - Table names (automatically adds prefix)
- `?p` - Raw parameter (use with caution)

```php
// Simple SELECT query
$db->query("SELECT * FROM ?t WHERE id = ?i", 'users', 1);
$user = $db->result();

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
```

### Fetching Results

```php
// Fetch all results as array of objects
$users = $db->query("SELECT * FROM ?t", 'users')->results();

// Fetch all results with primary key as array key
$users = $db->query("SELECT * FROM ?t", 'users')->results('id');

// Fetch single row as object
$user = $db->query("SELECT * FROM ?t WHERE id = ?i", 'users', 1)->result();

// Fetch single column value
$name = $db->query("SELECT name FROM ?t WHERE id = ?i", 'users', 1)->result('name');
```

### Table Prefixes

```php
$db->setPrefix('myapp');
// Will execute: SELECT * FROM `myapp_users`
$db->query("SELECT * FROM ?t", 'users');
```

### Error Handling and Logging

```php
// Configure logging
$db->setLogLocation('/path/to/logs')
   ->setLogErrors(true);

// Disable logging to throw exceptions instead
$db->setLogErrors(false);
```

## Error Logging

By default, errors are logged to `logs/db_errors.log` in the class directory. The log file automatically rotates when it reaches 1MB in size. Each log entry includes:

- Timestamp
- Error message
- SQL query (if applicable)
- Error code

## Requirements

- PHP 7.4 or higher
- PDO extension
- Appropriate database driver (mysql, pgsql, sqlite, etc.)

## License

This project is licensed under the MIT License - see the LICENSE file for details.