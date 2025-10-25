<?php

namespace Sylphian\Library\Logger;

use Psr\Log\LogLevel;
use XF\Mvc\Reply\Error;

final class Logger
{
	/**
	 * Creates an AddonLogger instance with a specified addon ID
	 *
	 * @param string $addonId
	 * @return AddonLogger
	 */
	public static function withAddonId(string $addonId): AddonLogger
	{
		return AddonLogger::create($addonId);
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string|\Stringable $message
	 * @param array $context
	 * @return void
	 */
	public static function log(mixed $level, string|\Stringable $message, array $context = []): void
	{
		$logger = AddonLogger::create();
		$logger->log($level, $message, $context);
	}

	/**
	 * System is unusable.
	 *
	 * @param string|\Stringable $message
	 * @param array $context
	 * @return void
	 */
	public static function emergency(string|\Stringable $message, array $context = []): void
	{
		self::log(LogLevel::EMERGENCY, $message, $context);
	}

	/**
	 * Action must be taken immediately.
	 *
	 *  Example: Entire website down, database unavailable, etc.
	 *
	 * @param string|\Stringable $message
	 * @param array $context
	 * @return void
	 */
	public static function alert(string|\Stringable $message, array $context = []): void
	{
		self::log(LogLevel::ALERT, $message, $context);
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string|\Stringable $message
	 * @param array $context
	 * @return void
	 */
	public static function critical(string|\Stringable $message, array $context = []): void
	{
		self::log(LogLevel::CRITICAL, $message, $context);
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string|\Stringable $message
	 * @param array $context
	 * @return void
	 */
	public static function error(string|\Stringable $message, array $context = []): void
	{
		self::log(LogLevel::ERROR, $message, $context);
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string|\Stringable $message
	 * @param array $context
	 * @return void
	 */
	public static function warning(string|\Stringable $message, array $context = []): void
	{
		self::log(LogLevel::WARNING, $message, $context);
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string|\Stringable $message
	 * @param array $context
	 * @return void
	 */
	public static function notice(string|\Stringable $message, array $context = []): void
	{
		self::log(LogLevel::NOTICE, $message, $context);
	}

	/**
	 * Interesting events.
	 *
	 * @param string|\Stringable $message
	 * @param array $context
	 * @return void
	 */
	public static function info(string|\Stringable $message, array $context = []): void
	{
		self::log(LogLevel::INFO, $message, $context);
	}


	/**
	 * Detailed debug information.
	 *
	 * @param string|\Stringable $message
	 * @param array $context
	 * @return void
	 */
	public static function debug(string|\Stringable $message, array $context = []): void
	{
		self::log(LogLevel::DEBUG, $message, $context);
	}

	/**
	 * Helper for creating logged error replies.
	 *
	 * Logs a message with a given level and returns a XenForo error reply.
	 * This centralizes logic for `logged*()` methods.
	 *
	 * @param string $level One of the PSR-3 log levels (LogLevel::*)
	 * @param \Stringable|string $error The error message to log and return
	 * @param array $context Additional context for the log entry
	 * @param int $code HTTP response code
	 *
	 * @return Error
	 */
	protected static function makeLoggedReply(string $level, \Stringable|string $error, array $context = [], int $code = 200): Error
	{
		self::log($level, (string) $error, $context);
		return new Error($error, $code);
	}

	/**
	 * Logs an emergency-level message and returns an error reply.
	 *
	 * Used for situations where the system is unusable or completely failed.
	 *
	 * @param \Stringable|string $error The emergency message to log and return
	 * @param array $context Additional context data for the log entry
	 * @param int $code HTTP response code
	 *
	 * @return Error
	 */
	public static function loggedEmergency(\Stringable|string $error, array $context = [], int $code = 200): Error
	{
		return self::makeLoggedReply(LogLevel::EMERGENCY, $error, $context, $code);
	}

	/**
	 * Logs an alert-level message and returns an error reply.
	 *
	 * Used for issues that require immediate attention (e.g. database down).
	 *
	 * @param \Stringable|string $error The alert message to log and return
	 * @param array $context Additional context data for the log entry
	 * @param int $code HTTP response code
	 *
	 * @return Error
	 */
	public static function loggedAlert(\Stringable|string $error, array $context = [], int $code = 200): Error
	{
		return self::makeLoggedReply(LogLevel::ALERT, $error, $context, $code);
	}

	/**
	 * Logs a critical-level message and returns an error reply.
	 *
	 * Used for critical failures such as missing dependencies or corrupted data.
	 *
	 * @param \Stringable|string $error The critical message to log and return
	 * @param array $context Additional context data for the log entry
	 * @param int $code HTTP response code
	 *
	 * @return Error
	 */
	public static function loggedCritical(\Stringable|string $error, array $context = [], int $code = 200): Error
	{
		return self::makeLoggedReply(LogLevel::CRITICAL, $error, $context, $code);
	}

	/**
	 * Logs an error-level message and returns an error reply.
	 *
	 * Used for recoverable runtime errors or failed operations.
	 *
	 * @param \Stringable|string $error The error message to log and return
	 * @param array $context Additional context data for the log entry
	 * @param int $code HTTP response code
	 *
	 * @return Error
	 */
	public static function loggedError(\Stringable|string $error, array $context = [], int $code = 200): Error
	{
		return self::makeLoggedReply(LogLevel::ERROR, $error, $context, $code);
	}

	/**
	 * Logs a warning-level message and returns an error reply.
	 *
	 * Used for non-critical issues or undesirable behavior that should be reviewed.
	 *
	 * @param \Stringable|string $error The warning message to log and return
	 * @param array $context Additional context data for the log entry
	 * @param int $code HTTP response code
	 *
	 * @return Error
	 */
	public static function loggedWarning(\Stringable|string $error, array $context = [], int $code = 200): Error
	{
		return self::makeLoggedReply(LogLevel::WARNING, $error, $context, $code);
	}

	/**
	 * Logs a notice-level message and returns an error reply.
	 *
	 * Used for significant but expected events (e.g. configuration changes).
	 *
	 * @param \Stringable|string $error The notice message to log and return
	 * @param array $context Additional context data for the log entry
	 * @param int $code HTTP response code
	 *
	 * @return Error
	 */
	public static function loggedNotice(\Stringable|string $error, array $context = [], int $code = 200): Error
	{
		return self::makeLoggedReply(LogLevel::NOTICE, $error, $context, $code);
	}

	/**
	 * Logs an info-level message and returns an error reply.
	 *
	 * Used for informational events such as user actions or general logs.
	 *
	 * @param \Stringable|string $error The info message to log and return
	 * @param array $context Additional context data for the log entry
	 * @param int $code HTTP response code
	 *
	 * @return Error
	 */
	public static function loggedInfo(\Stringable|string $error, array $context = [], int $code = 200): Error
	{
		return self::makeLoggedReply(LogLevel::INFO, $error, $context, $code);
	}

	/**
	 * Logs a debug-level message and returns an error reply.
	 *
	 * Used for diagnostic or debugging information during development.
	 *
	 * @param \Stringable|string $error The debug message to log and return
	 * @param array $context Additional context data for the log entry
	 * @param int $code HTTP response code
	 *
	 * @return Error
	 */
	public static function loggedDebug(\Stringable|string $error, array $context = [], int $code = 200): Error
	{
		return self::makeLoggedReply(LogLevel::DEBUG, $error, $context, $code);
	}
}
