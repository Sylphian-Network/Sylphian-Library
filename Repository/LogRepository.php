<?php

namespace Sylphian\Library\Repository;

use Sylphian\Library\Entity\AddonLog;
use Sylphian\Library\LogType;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class LogRepository extends Repository
{
	/**
	 * Generic log method that can be used with any log type
	 *
	 * @param LogType $type The type of log entry
	 * @param string $message The log message
	 * @param array|null $details For extra details
	 * @param string|null $addonId The addon ID (defaults to the calling addon)
	 * @return AddonLog
	 */
	public function log(LogType $type, string $message, ?array $details = null, ?string $addonId = null): AddonLog
	{
		if ($addonId === null)
		{
			$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
			$calledThroughHelper = false;

			if (isset($backtrace[1]['class']) && $backtrace[1]['class'] === self::class)
			{
				$helperMethods = ['logInfo', 'logWarning', 'logError', 'logDebug'];
				if (isset($backtrace[1]['function']) && in_array($backtrace[1]['function'], $helperMethods))
				{
					$calledThroughHelper = true;
				}
			}

			$addonId = $this->determineAddonId($calledThroughHelper ? 2 : 1);

			if ($addonId === null)
			{
				$addonId = 'XF';
			}
		}

		$addon = $this->em->find('XF:AddOn', $addonId);
		if (!$addon && $addonId !== 'XF')
		{
			$addonId = 'XF';
		}

		/** @var AddonLog $log */
		$log = $this->em->create('Sylphian\Library:AddonLog');
		$log->addon_id = $addonId;
		$log->type = $type->value;
		$log->content = $message;
		$log->date = \XF::$time;
		$log->user_id = \XF::visitor()->user_id ?: null;
		$log->details = $details;

		try
		{
			$log->save();
		}
		catch (PrintableException $e)
		{
			\XF::logError('Error saving addon log: ' . implode(', ', $e->getMessages()));

			if ($addonId !== 'XF')
			{
				return $this->log($type, $message, $details, 'XF');
			}
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Error saving addon log: ');
		}

		return $log;
	}

	/**
	 * Log an info message
	 *
	 * @param string $message The log message
	 * @param array|null $details For extra details
	 * @param string|null $addonId The addon ID (defaults to the calling addon)
	 * @return AddonLog
	 */
	public function logInfo(string $message, ?array $details = null, ?string $addonId = null): AddonLog
	{
		return $this->log(LogType::INFO, $message, $details, $addonId);
	}

	/**
	 * Log a warning message
	 *
	 * @param string $message The log message
	 * @param array|null $details For extra details
	 * @param string|null $addonId The addon ID (defaults to the calling addon)
	 * @return AddonLog
	 */
	public function logWarning(string $message, ?array $details = null, ?string $addonId = null): AddonLog
	{
		return $this->log(LogType::WARNING, $message, $details, $addonId);
	}

	/**
	 * Log an error message
	 *
	 * @param string $message The log message
	 * @param array|null $details For extra details
	 * @param string|null $addonId The addon ID (defaults to the calling addon)
	 * @return AddonLog
	 */
	public function logError(string $message, ?array $details = null, ?string $addonId = null): AddonLog
	{
		return $this->log(LogType::ERROR, $message, $details, $addonId);
	}

	/**
	 * Log a debug message
	 *
	 * @param string $message The log message
	 * @param array|null $details For extra details
	 * @param string|null $addonId The addon ID (defaults to the calling addon)
	 * @return AddonLog
	 */
	public function logDebug(string $message, ?array $details = null, ?string $addonId = null): AddonLog
	{
		return $this->log(LogType::DEBUG, $message, $details, $addonId);
	}

	/**
	 * Determine the addon ID from the call stack
	 *
	 * If logs are created in the library, the addon id will need to be manually specified.
	 *
	 * @param int $depth The backtrace depth to check
	 * @return string|null The determined addon ID or null if couldn't determine
	 */
	private function determineAddonId(int $depth = 2): ?string
	{
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

		$index = $depth;

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

					return $classParts[0] . '/' . $classParts[1];
				}
			}

			$index++;
		}

		return null;
	}

	/**
	 * Get logs for a specific addon
	 *
	 * @param string $addonId The addon ID
	 * @param int|null $page
	 * @param int|null $perPage
	 * @return array
	 */
	public function getLogsForAddon(string $addonId, ?int $page = 1, ?int $perPage = 20): array
	{
		$finder = $this->finder('Sylphian\Library:AddonLog')
			->where('addon_id', $addonId)
			->order('date', 'DESC');

		return $finder->limitByPage($page, $perPage)->fetch()->toArray();
	}

	/**
	 * Get logs of a specific type
	 *
	 * @param LogType $type The type of logs to retrieve
	 * @param int|null $page
	 * @param int|null $perPage
	 * @return array
	 */
	public function getLogsByType(LogType $type, ?int $page = 1, ?int $perPage = 20): array
	{
		$finder = $this->finder('Sylphian\Library:AddonLog')
			->where('type', $type->value)
			->order('date', 'DESC');

		return $finder->limitByPage($page, $perPage)->fetch()->toArray();
	}

	/**
	 * Get the total count of logs for an addon
	 *
	 * @param string $addonId The addon ID
	 * @return int
	 */
	public function getLogCountForAddon(string $addonId): int
	{
		return $this->finder('Sylphian\Library:AddonLog')
			->where('addon_id', $addonId)
			->total();
	}

	/**
	 * Check if there are any error logs in the system
	 *
	 * @return bool
	 */
	public function hasErrorLogs(): bool
	{
		$hasErrors = $this->db()->fetchOne('
        SELECT log_id
        FROM xf_addon_log
        WHERE type = ?
        LIMIT 1
    ', 'error');

		return (bool) $hasErrors;
	}

	/**
	 * Get all unique addons with their log counts
	 *
	 * @return array An array of addons with their log counts
	 */
	public function getUniqueAddonsWithCounts(): array
	{
		$db = $this->db();

		$addonCounts = $db->fetchAllKeyed(
			"SELECT 
        addon_id, 
        COUNT(*) AS log_count,
        SUM(IF(type = 'info', 1, 0)) AS info_count,
        SUM(IF(type = 'warning', 1, 0)) AS warning_count,
        SUM(IF(type = 'error', 1, 0)) AS error_count,
        SUM(IF(type = 'debug', 1, 0)) AS debug_count,
        MAX(date) AS latest_log_date
     FROM xf_addon_log 
     GROUP BY addon_id
     ORDER BY log_count DESC",
			'addon_id'
		);

		$addonIds = array_keys($addonCounts);
		$addons = $this->finder('XF:AddOn')
			->whereIds($addonIds)
			->fetch()
			->toArray();

		$result = [];
		foreach ($addonIds AS $addonId)
		{
			if (isset($addons[$addonId]))
			{
				$result[$addonId] = [
					'addon' => $addons[$addonId],
					'log_count' => $addonCounts[$addonId]['log_count'],
					'latest_log_date' => $addonCounts[$addonId]['latest_log_date'],
					'type_counts' => [
						'info' => $addonCounts[$addonId]['info_count'],
						'warning' => $addonCounts[$addonId]['warning_count'],
						'error' => $addonCounts[$addonId]['error_count'],
						'debug' => $addonCounts[$addonId]['debug_count'],
					],
				];
			}
			else
			{
				$result[$addonId] = [
					'addon_id' => $addonId,
					'addon' => null,
					'log_count' => $addonCounts[$addonId]['log_count'],
					'latest_log_date' => $addonCounts[$addonId]['latest_log_date'],
					'type_counts' => [
						'info' => $addonCounts[$addonId]['info_count'],
						'warning' => $addonCounts[$addonId]['warning_count'],
						'error' => $addonCounts[$addonId]['error_count'],
						'debug' => $addonCounts[$addonId]['debug_count'],
					],
				];
			}
		}

		return $result;
	}

	/**
	 * Clear all logs for a specific addon
	 *
	 * @param string $addonId The addon ID to clear logs for
	 * @return int Number of logs deleted
	 */
	public function clearLogsForAddon(string $addonId): int
	{
		$db = $this->db();
		return $db->delete('xf_addon_log', 'addon_id = ?', $addonId);
	}

	/**
	 * Get the configured log length for addon logs
	 *
	 * @return int
	 */
	public function getLogLength(): int
	{
		return $this->options()->addonLogLength;
	}

	/**
	 * Check if addon logging is enabled
	 *
	 * @return bool
	 */
	public function isEnabled(): bool
	{
		return $this->getLogLength() !== 0;
	}

	/**
	 * Get the cutoff timestamp for pruning
	 *
	 * @return int
	 */
	public function getCutOff(): int
	{
		return \XF::$time - 86400 * $this->getLogLength();
	}

	/**
	 * Prune old add-on logs based on addonLogLength setting
	 *
	 * @param int|null $cutOff Optional custom cutoff timestamp
	 * @return void
	 */
	public function pruneLogs(?int $cutOff = null): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$cutOff = $cutOff ?? $this->getCutOff();
		$debugEnabled = $this->options()->addonLogCleanupDebug ?? false;

		if ($debugEnabled)
		{
			$deletedCount = $this->db()->fetchOne('SELECT COUNT(*) FROM xf_addon_log WHERE date < ?', $cutOff);

			$this->db()->delete('xf_addon_log', 'date < ?', $cutOff);

			if ($deletedCount > 0)
			{
				$cutOffDate = date('Y-m-d H:i:s', $cutOff);

				$this->logDebug(
					'Addon logs pruned successfully',
					[
						'deleted_records' => $deletedCount,
						'cutoff_date' => $cutOffDate,
						'pruned_date' => date('Y-m-d H:i:s'),
					],
					'Sylphian/Library'
				);
			}
			else
			{
				$this->logDebug('No addon logs needed pruning', null, 'Sylphian/Library');
			}
		}
		else
		{
			$this->db()->delete(
				'xf_addon_log',
				'date < ?',
				$cutOff
			);
		}
	}
}
