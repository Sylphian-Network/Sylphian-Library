<?php

namespace Sylphian\Library\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Sylphian\Library\Entity\AddonLog;
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

	public function __construct(Manager $em, ?string $defaultAddonId = null)
	{
		$this->em = $em;
		$this->defaultAddonId = $defaultAddonId;
		$this->minLogLevel = \XF::options()->sylphian_library_min_log_level ?: LogLevel::DEBUG;
	}

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
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 */
	public function log($level, $message, array $context = []): void
	{
		if (self::$levelPriorities[$level] < self::$levelPriorities[$this->minLogLevel])
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
			if (!isset($details['exception']))
			{
				$details['exception'] = [];
			}
			$exception = $context['exception'];
			$details['exception']['class'] = get_class($exception);
			$details['exception']['message'] = $exception->getMessage();
			$details['exception']['code'] = $exception->getCode();
			$details['exception']['file'] = $exception->getFile();
			$details['exception']['line'] = $exception->getLine();
			$details['exception']['trace'] = $exception->getTraceAsString();
		}

		/** @var AddonLog $log */
		$log = $this->em->create('Sylphian\Library:AddonLog');
		$log->addon_id = $addonId;
		$log->type = $level;
		$log->content = $this->interpolate($message, $context);
		$log->date = \XF::$time;
		$log->user_id = \XF::visitor()->user_id ?: null;
		$log->details = !empty($details) ? $details : null;

		try
		{
			$log->save();

			if ($log->log_id)
			{
				/** @var WebhookRepository $webhookRepo */
				$webhookRepo = \XF::repository('XF:Webhook');

				$webhookRepo->queueWebhook(
					'syl_library_addon_log',
					$log->log_id,
					$level,
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
	public function loggedError(\Stringable|string $error, array $context = [], int $code = 200): Error
	{
		$this->log(LogLevel::ERROR, $error, $context);

		return new Error($error, $code);
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
	 * Determine the addon ID from the call stack
	 *
	 * Note: If logs are created in the library, the addon id will need to be manually specified.
	 *
	 * @return string The determined addon ID (fallback to 'XF' if couldn't determine)
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
