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
	 * Logs an error message and returns an Error reply
	 *
	 * Used in controllers to save calling a log and return an error reply
	 *
	 * @param \Stringable|string $error The error message to log and return
	 * @param array $context Additional context data for the log entry
	 * @param int $code HTTP response code
	 *
	 * @return Error
	 */
	public static function loggedError(\Stringable|string $error, array $context = [], int $code = 200): Error
	{
		self::error((string) $error, $context);

		return new Error($error, $code);
	}
}
