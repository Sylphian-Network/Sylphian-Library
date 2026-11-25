<?php

namespace Sylphian\Library\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Sylphian\Library\Entity\AddonLog;
use XF\Db\AbstractAdapter;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Reply\Error;
use XF\PrintableException;
use XF\Repository\WebhookRepository;

class AddonLogger implements LoggerInterface
{
	/**
	 * @var Manager Entity manager
	 */
	protected Manager $em;

	/**
	 * @var string|null Default add-on ID if not specified in context
	 */
	protected ?string $defaultAddonId;

	/**
	 * @var string Minimum log level to record
	 */
	protected string $minLogLevel;

	protected bool $joinTransaction = false;

	protected static ?AbstractAdapter $detachedDb = null;

	public function __construct(Manager $em, ?string $defaultAddonId = null)
	{
		$this->em = $em;
		$this->defaultAddonId = $defaultAddonId;
		$this->minLogLevel = \XF::options()->sylphian_library_min_log_level ?: LogLevel::DEBUG;
	}

	protected const MAX_DETAILS_BYTES = 60000;
	protected const MAX_DETAIL_STRING_BYTES = 2000;
	protected const MAX_CONTENT_BYTES = 65000;
	protected const MAX_TRACE_LINES = 50;

	/**
	 * Log level priorities (lower is less severe)
	 */
	protected static array $levelPriorities = [
		LogLevel::DEBUG     => 0,
		LogLevel::INFO      => 1,
		LogLevel::NOTICE    => 2,
		LogLevel::WARNING   => 3,
		LogLevel::ERROR     => 4,
		LogLevel::CRITICAL  => 5,
		LogLevel::ALERT     => 6,
		LogLevel::EMERGENCY => 7,
	];

	/**
	 * Creates a new AddonLogger instance with proper class extension support
	 *
	 * @param string|null $defaultAddonId Default add-on ID if not specified in context
	 * @return static
	 */
	public static function create(?string $defaultAddonId = null): static
	{
		$loggerClass = \XF::extendClass(self::class);
		return new $loggerClass(\XF::em(), $defaultAddonId);
	}

	/**
	 * Returns a copy that writes on the caller's connection even inside a transaction, so its
	 * entries commit or roll back with the caller's work. Use it only when they must be all-or-nothing.
	 */
	public function joinTransaction(bool $join = true): static
	{
		$clone = clone $this;
		$clone->joinTransaction = $join;

		return $clone;
	}

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function emergency($message, array $context = []): void
	{
		$this->log(LogLevel::EMERGENCY, $message, $context);
	}

	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function alert($message, array $context = []): void
	{
		$this->log(LogLevel::ALERT, $message, $context);
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function critical($message, array $context = []): void
	{
		$this->log(LogLevel::CRITICAL, $message, $context);
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function error($message, array $context = []): void
	{
		$this->log(LogLevel::ERROR, $message, $context);
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function warning($message, array $context = []): void
	{
		$this->log(LogLevel::WARNING, $message, $context);
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function notice($message, array $context = []): void
	{
		$this->log(LogLevel::NOTICE, $message, $context);
	}

	/**
	 * Interesting events.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function info($message, array $context = []): void
	{
		$this->log(LogLevel::INFO, $message, $context);
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array $context
	 */
	public function debug($message, array $context = []): void
	{
		$this->log(LogLevel::DEBUG, $message, $context);
	}

	/**
	 * Never throws for a bad level, unlike PSR-3, so an error that is being logged is not lost.
	 * An unknown level is logged as `error`, and the caller's value (or its type, when it has no
	 * string form) goes in `details.original_level`.
	 *
	 * @param mixed $level a LogLevel constant, in any case
	 * @param string|\Stringable $message
	 * @param array $context
	 */
	public function log($level, $message, array $context = []): void
	{
		$originalLevel = is_scalar($level) || $level instanceof \Stringable ? (string) $level : get_debug_type($level);
		$level = strtolower(trim($originalLevel));
		if (!isset(self::$levelPriorities[$level]))
		{
			$context['original_level'] = $originalLevel;
			$level = LogLevel::ERROR;
		}

		$minPriority = self::$levelPriorities[$this->minLogLevel] ?? 0;
		if (self::$levelPriorities[$level] < $minPriority)
		{
			return;
		}

		$addonId = $context['addon_id'] ?? $this->defaultAddonId ?? $this->determineAddonId();

		$addon = $this->em->find('XF:AddOn', $addonId);
		if (!$addon)
		{
			$addonId = 'XF';
		}

		$details = $context;
		unset($details['addon_id']);

		if (isset($context['exception']) && $context['exception'] instanceof \Throwable)
		{
			$exception = $context['exception'];
			$details['exception'] = [
				'class' => get_class($exception),
				'message' => $exception->getMessage(),
				'code' => $exception->getCode(),
				'file' => $exception->getFile(),
				'line' => $exception->getLine(),
				'trace' => $this->limitTrace($exception->getTraceAsString()),
			];
		}

		/** @var AddonLog $log */
		$log = $this->em->create('Sylphian\Library:AddonLog');
		$log->addon_id = $addonId;
		$log->type = $level;
		$log->content = $this->limitBytes($this->interpolate($message, $context), self::MAX_CONTENT_BYTES);
		$log->date = \XF::$time;
		$log->user_id = \XF::visitor()->user_id ?: null;
		$log->details = $details ? $this->limitDetails($details) : null;

		if ($this->joinTransaction || !$this->em->getDb()->inTransaction())
		{
			$this->saveEntity($log, $level, $addonId);

			return;
		}

		try
		{
			if ($this->saveDetached($log, $level))
			{
				return;
			}
		}
		catch (\Throwable $e)
		{
			\XF::logException($e, false, 'Error saving add-on log on its own connection: ');
		}

		$this->saveEntity($log, $level, $addonId);
	}

	/**
	 * Returns false without writing when the entry fails validation, so the caller can fall back.
	 */
	protected function saveDetached(AddonLog $log, string $level): bool
	{
		if (!$log->preSave())
		{
			return false;
		}

		// Bypasses the entity on purpose: saving it would write on the main connection.
		$row = [];
		foreach ($log->getNewValues() AS $column => $value)
		{
			$row[$column] = $this->em->encodeValueForSource($log->structure()->columns[$column]['type'], $value);
		}

		$db = static::getDetachedConnection();
		$db->insert('xf_addon_log', $row);
		$logId = (int) $db->lastInsertId();

		// Webhooks are queued on the main connection, so they wait until the caller's transaction is over.
		\XF::runLater(function () use ($logId, $level)
		{
			$entry = \XF::em()->find(AddonLog::class, $logId);
			if (!$entry)
			{
				return;
			}

			$webhookRepo = \XF::repository(WebhookRepository::class);
			$webhookRepo->queueWebhook('syl_library_addon_log', $logId, 'insert', $entry);
			$webhookRepo->queueWebhook('syl_library_addon_log', $logId, $level . '_create', $entry);
		});

		return true;
	}

	protected static function getDetachedConnection(): AbstractAdapter
	{
		if (!static::$detachedDb)
		{
			$config = \XF::config();
			$dbConfig = $config['db'];
			$adapterClass = $dbConfig['adapterClass'];
			unset($dbConfig['adapterClass']);

			$db = new $adapterClass($dbConfig, $config['fullUnicode']);

			// The caller's transaction may hold locks this insert waits on, and it cannot release them
			// until the log call returns, so give up quickly and fall back to the caller's connection.
			$db->query('SET SESSION innodb_lock_wait_timeout = 5');

			static::$detachedDb = $db;
		}

		return static::$detachedDb;
	}

	protected function saveEntity(AddonLog $log, string $level, string $addonId): void
	{
		$webhookRepo = \XF::repository(WebhookRepository::class);

		try
		{
			$log->save();

			if ($log->log_id)
			{
				$webhookRepo->queueWebhook(
					'syl_library_addon_log',
					$log->log_id,
					$level . '_create',
					$log
				);
			}
		}
		catch (PrintableException $e)
		{
			\XF::logError('Error saving add-on log: ' . implode(', ', $e->getMessages()));

			if ($addonId !== 'XF')
			{
				$log->set('addon_id', 'XF', ['forceSet' => true]);
				try
				{
					$log->save();

					if ($log->log_id)
					{
						$webhookRepo->queueWebhook(
							'syl_library_addon_log',
							$log->log_id,
							$level . '_create',
							$log
						);
					}
				}
				catch (\Exception $e)
				{
					\XF::logException($e, false, 'Error saving add-on log: ');
				}
			}
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error saving add-on log: ');
		}
	}

	/**
	 * Helper for creating logged error replies.
	 *
	 * Logs a message with a given level and returns a XenForo error reply.
	 * This centralizes logic for `logged*()` methods.
	 *
	 * @param string $level One of the PSR-3 log levels (LogLevel::*)
	 * @param \Stringable|string $message The error message to log and return
	 * @param array $context Additional context for the log entry
	 * @param int $code HTTP response code
	 *
	 * @return Error
	 */
	protected function makeLoggedReply(string $level, \Stringable|string $message, array $context = [], int $code = 200): Error
	{
		$this->log($level, $message, $context);

		return new Error($message, $code);
	}

	/**
	 * Logs a debug-level message and returns an Error reply.
	 *
	 * @param \Stringable|string $debug   The debug message or object to log and include in the reply.
	 * @param array              $context Additional context for interpolation in the log message.
	 * @param int                $code    The HTTP status code for the returned error response.
	 *
	 * @return Error
	 */
	public function loggedDebug(\Stringable|string $debug, array $context = [], int $code = 200): Error
	{
		return $this->makeLoggedReply(LogLevel::DEBUG, $debug, $context, $code);
	}

	/**
	 * Logs an info-level message and returns an Error reply.
	 *
	 * @param \Stringable|string $info   The info message or object to log and include in the reply.
	 * @param array              $context Additional context for interpolation in the log message.
	 * @param int                $code    The HTTP status code for the returned error response.
	 *
	 * @return Error
	 */
	public function loggedInfo(\Stringable|string $info, array $context = [], int $code = 200): Error
	{
		return $this->makeLoggedReply(LogLevel::INFO, $info, $context, $code);
	}

	/**
	 * Logs a notice-level message and returns an Error reply.
	 *
	 * @param \Stringable|string $notice   The notice message or object to log and include in the reply.
	 * @param array              $context Additional context for interpolation in the log message.
	 * @param int                $code    The HTTP status code for the returned error response.
	 *
	 * @return Error
	 */
	public function loggedNotice(\Stringable|string $notice, array $context = [], int $code = 200): Error
	{
		return $this->makeLoggedReply(LogLevel::NOTICE, $notice, $context, $code);
	}

	/**
	 * Logs a warning-level message and returns an Error reply.
	 *
	 * @param \Stringable|string $warning   The warning message or object to log and include in the reply.
	 * @param array              $context Additional context for interpolation in the log message.
	 * @param int                $code    The HTTP status code for the returned error response.
	 *
	 * @return Error
	 */
	public function loggedWarning(\Stringable|string $warning, array $context = [], int $code = 200): Error
	{
		return $this->makeLoggedReply(LogLevel::WARNING, $warning, $context, $code);
	}

	/**
	 * Logs an error-level message and returns an Error reply.
	 *
	 * @param \Stringable|string $error   The error message or object to log and include in the reply.
	 * @param array              $context Additional context for interpolation in the log message.
	 * @param int                $code    The HTTP status code for the returned error response.
	 *
	 * @return Error
	 */
	public function loggedError(\Stringable|string $error, array $context = [], int $code = 200): Error
	{
		return $this->makeLoggedReply(LogLevel::ERROR, $error, $context, $code);
	}

	/**
	 * Logs a critical-level message and returns an Error reply.
	 *
	 * @param \Stringable|string $critical   The critical message or object to log and include in the reply.
	 * @param array              $context Additional context for interpolation in the log message.
	 * @param int                $code    The HTTP status code for the returned error response.
	 *
	 * @return Error
	 */
	public function loggedCritical(\Stringable|string $critical, array $context = [], int $code = 500): Error
	{
		return $this->makeLoggedReply(LogLevel::CRITICAL, $critical, $context, $code);
	}

	/**
	 * Logs an alert-level message and returns an Error reply.
	 *
	 * @param \Stringable|string $alert   The alert message or object to log and include in the reply.
	 * @param array              $context Additional context for interpolation in the log message.
	 * @param int                $code    The HTTP status code for the returned error response.
	 *
	 * @return Error
	 */
	public function loggedAlert(\Stringable|string $alert, array $context = [], int $code = 500): Error
	{
		return $this->makeLoggedReply(LogLevel::ALERT, $alert, $context, $code);
	}

	/**
	 * Logs an emergency-level message and returns an Error reply.
	 *
	 * @param \Stringable|string $emergency   The emergency message or object to log and include in the reply.
	 * @param array              $context Additional context for interpolation in the log message.
	 * @param int                $code    The HTTP status code for the returned error response.
	 *
	 * @return Error
	 */
	public function loggedEmergency(\Stringable|string $emergency, array $context = [], int $code = 500): Error
	{
		return $this->makeLoggedReply(LogLevel::EMERGENCY, $emergency, $context, $code);
	}

	/**
	 * Interpolates context values into the message placeholders.
	 *
	 * @param string $message
	 * @param array $context
	 * @return string
	 */
	private function interpolate(string $message, array $context = []): string
	{
		$replace = [];
		foreach ($context AS $key => $val)
		{
			if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString')))
			{
				$replace['{' . $key . '}'] = $val;
			}
		}

		return strtr($message, $replace);
	}

	/**
	 * Makes the details safe to save: invalid UTF-8 is replaced, and anything over
	 * MAX_DETAILS_BYTES is cut down, marked with `_truncated`, so the entry itself is never lost.
	 * The exception block is the last thing dropped.
	 */
	protected function limitDetails(array $details): array
	{
		// The entity rejects invalid UTF-8 on assignment, before the save's try block.
		$encoded = json_encode($details, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
		$details = $encoded !== false ? json_decode($encoded, true) : null;
		if (!is_array($details))
		{
			return ['_truncated' => true];
		}

		if ($this->fitsDetailsColumn($details))
		{
			return $details;
		}

		foreach ($details AS $key => $value)
		{
			if ($key !== 'exception')
			{
				$details[$key] = $this->limitStrings($value);
			}
		}
		$details['_truncated'] = true;

		if ($this->fitsDetailsColumn($details))
		{
			return $details;
		}

		$details = array_intersect_key($details, ['exception' => true, '_truncated' => true]);
		if ($this->fitsDetailsColumn($details))
		{
			return $details;
		}

		$details = $this->limitStrings($details);

		return $this->fitsDetailsColumn($details) ? $details : ['_truncated' => true];
	}

	protected function fitsDetailsColumn(array $details): bool
	{
		// Measured with the same flags the entity saves with.
		return strlen((string) json_encode($details, JSON_PARTIAL_OUTPUT_ON_ERROR)) <= self::MAX_DETAILS_BYTES;
	}

	protected function limitStrings(mixed $value): mixed
	{
		if (is_array($value))
		{
			array_walk_recursive($value, function (&$item)
			{
				$item = $this->limitStrings($item);
			});

			return $value;
		}

		return is_string($value) ? $this->limitBytes($value, self::MAX_DETAIL_STRING_BYTES) : $value;
	}

	protected function limitBytes(string $value, int $maxBytes): string
	{
		if (strlen($value) <= $maxBytes)
		{
			return $value;
		}

		return mb_strcut($value, 0, $maxBytes - 3, 'UTF-8') . '...';
	}

	protected function limitTrace(string $trace): string
	{
		$lines = explode("\n", $trace);
		if (count($lines) <= self::MAX_TRACE_LINES)
		{
			return $trace;
		}

		$more = count($lines) - self::MAX_TRACE_LINES;

		return implode("\n", array_slice($lines, 0, self::MAX_TRACE_LINES)) . "\n... ($more more)";
	}

	/**
	 * Library frames are skipped, so an entry logged by the Library itself must pass its add-on ID.
	 *
	 * @return string the first installed add-on on the call stack, or 'XF' if there is none
	 */
	private function determineAddonId(): string
	{
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

		$index = 2;

		while (isset($backtrace[$index]))
		{
			$caller = $backtrace[$index];

			if (isset($caller['class']))
			{
				$classParts = explode('\\', $caller['class']);

				if (count($classParts) >= 2)
				{
					if ($classParts[0] === 'Sylphian' && $classParts[1] === 'Library')
					{
						$index++;
						continue;
					}

					$addonId = $classParts[0] . '/' . $classParts[1];
					$addon = $this->em->find('XF:AddOn', $addonId);
					if ($addon)
					{
						return $addonId;
					}
				}
			}

			$index++;
		}

		return 'XF';
	}
}
