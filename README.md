# Solo Database
[![Version](https://img.shields.io/badge/version-2.8.0-blue.svg)](https://github.com/solophp/database)
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

## Usage

### Basic Configuration
```php
use Solo\Database\{Config, Connection};
use Solo\Logger;
use PDO;

// Configure database connection with object fetch mode
$config = new Config(
    hostname: 'localhost',
    username: 'user',
    password: 'pass',
    dbname: 'mydb',
    prefix: 'prefix_',
    fetchMode: PDO::FETCH_OBJ  // Or PDO::FETCH_ASSOC for arrays
);

// Create connection with optional logging
$logger = new Logger('/path/to/logs/db.log');
$connection = new Connection($config, $logger);
$db = new Database($connection);
```

### Query Examples
```php
// 1) Basic SELECT
$users = $db->query("SELECT * FROM ?t", 'users')->fetchAll();
// By default, rows are fetched with the configured fetch mode (e.g., PDO::FETCH_OBJ)

// 2) INSERT with an associative array
$userData = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
    'created_at' => new DateTimeImmutable()
];
$db->query("INSERT INTO ?t SET ?A", 'users', $userData);

// 3) INSERT multiple rows (bulk INSERT)
$data = [
    ['John', 30],
    ['Alice', 25],
];
$db->query("INSERT INTO ?t (name, age) VALUES ?M", 'users', $data);
// Generates: INSERT INTO `prefix_users` (name, age) VALUES ('John', 30), ('Alice', 25)

// 4) Inserting with optional date fields
$userData = [
    'name' => 'John Doe',
    'created_at' => new DateTimeImmutable(),  // Specific date
    'updated_at' => null,                     // Null is now supported
    'last_login' => null
];
$db->query("INSERT INTO ?t SET ?A", 'users', $userData);

// 5) SELECT with different fetch modes
// Using default fetch mode
$usersDefault = $db->query("SELECT * FROM ?t", 'users')->fetchAll();

// Override fetch mode for a specific query
$userAssoc = $db->query("SELECT * FROM ?t WHERE id = ?i", 'users', 1)
    ->fetch(PDO::FETCH_ASSOC);

// 6) SELECT with IN clause
$ids = [1, 2, 3];
$result = $db->query("SELECT * FROM ?t WHERE id IN ?a", 'users', $ids)
    ->fetchAll();

// 7) SELECT with a dynamic column name
$column = 'email';
$userEmail = $db->query("SELECT ?c FROM ?t WHERE id = ?i", $column, 'users', 1)
    ->fetch();

// 8) Transactions example
try {
    $db->beginTransaction();
    
    // Insert
    $orderData = ['product' => 'Laptop', 'quantity' => 1, 'user_id' => 1];
    $db->query("INSERT INTO ?t SET ?A", 'orders', $orderData);
    
    // Update
    $amount = 799.99;
    $userId = 1;
    $db->query(
        "UPDATE ?t SET balance = balance - ?f WHERE id = ?i", 
        'accounts', 
        $amount, 
        $userId
    );
    
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}

// 9) Prepare a query without executing it
$sql = $db->prepare(
    "SELECT * FROM ?t WHERE user_id = ?i AND status = ?s", 
    'orders',
    15,
    'pending'
);
// Returns a prepared SQL string, e.g.:
// SELECT * FROM prefix_orders WHERE user_id = 15 AND status = 'pending'

// 10) Single Column Fetch
// Fetch the first column from the next row in the result set
$email = $db->query("SELECT email FROM ?t WHERE id = ?i", 'users', 1)->fetchColumn();
if ($email !== false) {
    echo "User email: $email";
} else {
    echo "No user found!";
}

// If your query returns multiple columns, specify the column index:
$db->query("SELECT id, email FROM ?t WHERE id = ?i", 'users', 2);
$id = $db->fetchColumn(0);    // 'id' column
$email = $db->fetchColumn(1); // 'email' column
```

## Query Placeholders
- `?s` - String (safely quoted)
- `?i` - Integer
- `?f` - Float
- `?a` - Array (for IN statements)
- `?A` - Associative Array (for SET statements)
- `?t` - Table name (with prefix)
- `?c` - Column name (safely quoted)
- `?d` - Date (expects DateTimeImmutable or null, formats according to database type)
- `?l` - Like condition (adds % wildcards)
- `?M` - Array of arrays (for multi-row INSERT queries)
- `?r` - Raw parameter (unescaped)

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
- `fetchAll(?int $fetchMode = null)`: Returns `array<int|string, array|stdClass>`
- `fetch(?int $fetchMode = null)`: Returns `array|stdClass|null`
- `fetchColumn(int $columnIndex = 0)`: Returns `mixed` (the value of the column or false if no more rows)
- `rowCount()`: Returns `int`
- `lastInsertId()`: Returns `string|false`

## Error Handling
- All database operations are wrapped in try-catch blocks
- Throws exceptions with detailed error messages
- Automatically logs errors when a logger is configured

## License
MIT License. See LICENSE file for details.