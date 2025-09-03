# Sylphian-Library

Sylphian Library is a utility add-on for XenForo that provides centralised logging functionality for other add-ons. It allows developers to create, store, and manage logs from their add-ons with different severity levels and additional contextual information.

### Features

- Standardised logging interface for XenForo add-ons
- Support for multiple log types: INFO, WARNING, ERROR, and DEBUG
- Ability to store additional contextual data with each log entry
- Admin interface for viewing and managing logs
- Automatic add-on identification

---

## Usage

The Sylphian Library provides a standardised logging system that follows the PSR-3 logging interface. This section explains how to integrate and use logging functionality in your XenForo add-ons.

### Basic Usage

To start logging from your add-on, you can create a logger instance directly:

```php
use Sylphian\Library\Logger\AddonLogger;

// Create a logger instance
$logger = new AddonLogger(\XF::em());

// Log messages with different severity levels
$logger->info("User profile updated successfully", ["user_id" => 123]);
$logger->warning("Invalid form submission attempt", ["form_data" => $formData]);
$logger->error("Failed to process payment", ["transaction_id" => "txn_123456"]);
```

### Using LoggableTrait

For more convenient logging from within your classes, use the LoggableTrait:

```php
use Sylphian\Library\Logger\LoggableTrait;

class MyService
{
    use LoggableTrait;
    
    public function doSomething()
    {
        $logger = $this->getLogger()
        $logger->info("Operation started");
        
        try {
            $result = $this->processData();
            $logger->info("Data processed successfully", ["result" => $result]);
        } catch (\Exception $e) {
            $logger->error("Error during operation", [
                "exception" => $e,
                "details" => ["custom_info" => "Additional context"]
            ]);
        }
    }
}
```

### Available Log Levels

The logger supports the following severity levels:

1. `Emergency` - System is unusable
2. `Alert` - Action must be taken immediately
3. `Critical` - Critical conditions
4. `Error` - Error conditions
5. `Warning` - Warning conditions
6. `Notice` - Normal but significant condition
7. `Info` - Informational messages
8. `Debug` - Debug-level messages

### Defining Add-on

By default, `AddonLogger` automatically attempts to determine which add-on is creating logs by analyzing the call stack:

1. It examines the namespace of the calling code (up to 10 levels in the call stack)
2. It extracts the first two parts of the namespace (e.g., `Vendor\Addon` from `Vendor\Addon\Service\UserService`)
3. It verifies if a valid add-on with that ID exists in the XenForo system
4. If found, it uses that add-on ID for the log entry

For example, if your code is in the namespace `Vendor\Addon\Service`, the logger will automatically detect `Vendor\Addon` as the add-on ID.

```php
namespace Vendor\Addon\Service;

use Sylphian\Library\Logger\AddonLogger;

class UserService
{
    public function registerUser($userData)
    {
        $logger = new AddonLogger(\XF::em());
        // The add-on ID will be automatically detected as "Vendor\Addon"
        $logger->info("New user registration", ["user_data" => $userData]);
    }
}
```

#### When automatic detection fails

There are several scenarios where automatic detection might not work correctly:

1. When your code is in a namespace that doesn't match your add-on ID structure
2. When logging from a global scope or a script without a namespace
3. When the library can't determine the correct add-on from the call stack
4. When code from one add-on needs to log on behalf of another add-on

In these cases, you should explicitly specify the add-on ID.

#### Specifying Add-on ID

You can specify the add-on ID in three different ways:

##### When Creating the Logger Instance

```php
use Sylphian\Library\Logger\AddonLogger;

// Specify the add-on ID as the second parameter
$logger = new AddonLogger(\XF::em(), "Vendor/Addon");
$logger->info("Configuration updated");
```

This is useful when all logs from this logger instance should be attributed to the same add-on.

##### When Using LoggableTrait

If your class uses the `LoggableTrait`, you can specify the add-on ID when calling `getLogger()`:

```php
use Sylphian\Library\Logger\LoggableTrait;

class MyService
{
use LoggableTrait;

    public function processData()
    {
        // Specify the add-on ID when getting the logger
        $logger = $this->getLogger("Vendor/Addon");
        $logger->info("Processing started");
        
        // All subsequent uses of this logger will use the same add-on ID
        $logger->info("Processing completed");
    }
}
```

##### In the Context Array (Per Log Entry)

You can also specify the add-on ID in the context array for a specific log entry:

```php
$logger->warning("Something unusual happened", [
    "addon_id" => "Vendor/Addon",
    "additional_data" => $someData
]);
```

This approach is useful when a single service needs to log on behalf of multiple add-ons, or when you need to override the add-on ID for a specific log entry.

### Log Retention

Logs are automatically pruned based on the configured retention period in the Admin CP settings. You can adjust this setting to keep logs for longer or shorter periods based on your requirements.