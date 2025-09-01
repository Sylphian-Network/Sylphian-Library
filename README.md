# Sylphian-Library

Sylphian Library is a utility add-on for XenForo that provides centralised logging functionality for other add-ons. It allows developers to create, store, and manage logs from their add-ons with different severity levels and additional contextual information.

### Features

- Standardised logging interface for XenForo add-ons
- Support for multiple log types: INFO, WARNING, ERROR, and DEBUG
- Ability to store additional contextual data with each log entry
- Admin interface for viewing and managing logs
- Automatic add-on identification

## Usage

### Basic Logging

To use Sylphian Library for logging in your add-on, first import the required classes:

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

You can add additional data to your logs by passing an array as the second parameter:

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

By default, the library will automatically determine which add-on is creating the log. However, you can manually specify the add-on ID if needed:

```php
$logRepo->logError(
    'Database connection failed',
    ['connection_details' => $connectionInfo],
    'MyAddon'
);
```

Note: When manually specifying an add-on ID, it must be a valid add-on ID that exists in the XenForo database’s xf_addon table. If the specified add-on ID does not exist in the system, the log will either be created with an “XF” add-on ID or an error will be thrown during the save process.