# Sylphian-Library

Sylphian Library is a utility addon for XenForo that provides centralized logging functionality for other addons. It allows developers to create, store, and manage logs from their addons with different severity levels and additional context information.

### Features

- Standardized logging interface for XenForo addons
- Support for multiple log types: INFO, WARNING, ERROR, and DEBUG
- Ability to store additional context data with each log entry
- Admin interface for viewing and managing logs
- Automatic addon identification

## Usage

### Basic Logging

To use Sylphian Library for logging in your addon, first import the required classes:

```php
use Sylphian\Library\LogType;
use Sylphian\Library\Repository\LogRepository;
```

Then, get an instance of the Log Repository:

```php
/** @var LogRepository $logRepo */
$logRepo = $this->repository('Sylphian\Library:Log');
```

### Creating Logs

There are several ways to create logs:

#### Convenience Methods

For common log types, use these convenience methods:

```php
// Create an info log
$logRepo->logInfo('This is an informational message');

// Create a warning log
$logRepo->logWarning('This is a warning message');

// Create an error log
$logRepo->logError('This is an error message');

// Create a debug log
$logRepo->logDebug('This is a debug message for troubleshooting');
```

#### Generic Log Method

For more control, use the generic log method with a LogType enum:

```php
$logRepo->log(LogType::INFO, 'This is an informational message', null);
```

### Adding Context Data

You can add additional context data to your logs by passing an array as the second parameter:

```php
$logRepo->logInfo(
    'User details fetched', 
    [
        'user_id' => 123,
        'email' => 'user@example.com',
        'ip' => '127.0.0.1'
    ]
);
```

### Specifying Addon ID

By default, the library will automatically determine which addon is creating the log. However, you can manually specify the addon ID if needed:

```php
$logRepo->logError(
    'Database connection failed',
    ['connection_details' => $connectionInfo],
    'MyAddon'
);
```

**Note:** When manually specifying an addon ID, it must be a valid addon ID that exists in the XenForo database's `xf_addon` table. If the specified addon ID doesn't exist in the system, the log will either be created with an "Unknown" addon ID or an error will be thrown during the save process.