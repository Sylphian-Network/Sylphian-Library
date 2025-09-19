<?php

namespace Sylphian\Library\Repository;

use Sylphian\Library\AddonPermissionHandler;
use Sylphian\Library\Logger\Logger;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class LogRepository extends Repository
{
	/**
	 * Get logs for a specific addon with optional filtering
	 *
	 * @param string $addonId The addon ID
	 * @param int|null $page
	 * @param int|null $perPage
	 * @param array $filters Optional filters: start_date, end_date, type, user_id
	 * @return array
	 */
	public function getLogsForAddon(string $addonId, ?int $page = 1, ?int $perPage = 20, array $filters = []): array
	{
		$finder = $this->getAddonLogFinder($addonId, $filters)
			->order('date', 'DESC');

		return $finder->limitByPage($page, $perPage)->fetch()->toArray();
	}

	/**
	 * Get all unique log types and users for an addon
	 *
	 * @param string $addonId The addon ID
	 * @return array
	 */
	public function getAllAddonLogTypesAndUsers(string $addonId): array
	{
		$finder = $this->finder('Sylphian\Library:AddonLog')
			->where('addon_id', $addonId);

		$types = [];
		$users = [];
		$userIds = [];

		foreach ($finder->fetch() AS $log)
		{
			$types[$log->type] = $log->type;

			if ($log->user_id)
			{
				$userIds[$log->user_id] = $log->user_id;
			}
			else
			{
				$users['System'] = 'System';
			}
		}

		if (!empty($userIds))
		{
			$userFinder = $this->finder('XF:User')
				->whereIds($userIds);

			foreach ($userFinder->fetch() AS $user)
			{
				$users[$user->user_id] = $user->username;
			}
		}

		return [
			'types' => $types,
			'users' => $users,
		];
	}

	/**
	 * Get the total count of logs for an addon with optional filtering
	 *
	 * @param string $addonId The addon ID
	 * @param array $filters Optional filters: start_date, end_date, type, user_id
	 * @return int
	 */
	public function getLogCountForAddon(string $addonId, array $filters = []): int
	{
		$finder = $this->getAddonLogFinder($addonId, $filters);
		return $finder->total();
	}

	private function getAddonLogFinder(string $addonId, array $filters = []): Finder
	{
		$finder = $this->finder('Sylphian\Library:AddonLog')
			->where('addon_id', $addonId);

		if (!empty($filters['start_date']))
		{
			$finder->where('date', '>=', $filters['start_date']);
		}
		if (!empty($filters['end_date']))
		{
			$finder->where('date', '<=', $filters['end_date']);
		}
		if (!empty($filters['type']))
		{
			$type = $filters['type'];

			if (is_array($type) && !in_array('any', $type))
			{
				$typeConditions = [];
				foreach ($type AS $t)
				{
					$typeConditions[] = ['type', $t];
				}

				if (count($typeConditions) > 0)
				{
					$finder->whereOr($typeConditions);
				}
			}
		}
		if (!empty($filters['user_id']))
		{
			$userId = $filters['user_id'];

			if (is_array($userId) && !in_array('0', $userId) && !in_array('any', $userId))
			{
				$userConditions = [];
				foreach ($userId AS $uid)
				{
					if ($uid === 'System' || $uid === 'system')
					{
						$userConditions[] = ['user_id', null];
					}
					else
					{
						$userConditions[] = ['user_id', $uid];
					}
				}

				if (count($userConditions) > 0)
				{
					$finder->whereOr($userConditions);
				}
			}
		}

		return $finder;
	}

	/**
	 * Fetches high-priority log counts
	 *
	 * The types of errors it checks are 'emergency', 'critical', 'alert', 'error'
	 *
	 * @return array|null an array of counts or null if no matching these types are found
	 */
	public function getHighPriorityLogCounts(): ?array
	{
		$finder = $this->finder('Sylphian\Library:AddonLog')
			->where('type', ['emergency', 'critical', 'alert', 'error']);

		if ($finder->total() <= 0)
		{
			return null;
		}

		return [
			'emergency_count' => (clone $finder)->where('type', 'emergency')->total(),
			'critical_count'  => (clone $finder)->where('type', 'critical')->total(),
			'alert_count'     => (clone $finder)->where('type', 'alert')->total(),
			'error_count'     => (clone $finder)->where('type', 'error')->total(),
		];
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
        SUM(IF(type = 'emergency', 1, 0)) AS emergency_count,
        SUM(IF(type = 'alert', 1, 0)) AS alert_count,
        SUM(IF(type = 'critical', 1, 0)) AS critical_count,
        SUM(IF(type = 'error', 1, 0)) AS error_count,
        SUM(IF(type = 'warning', 1, 0)) AS warning_count,
        SUM(IF(type = 'notice', 1, 0)) AS notice_count,
        SUM(IF(type = 'info', 1, 0)) AS info_count,
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
			if (!AddonPermissionHandler::canViewAddonLogs(null, $addonId))
			{
				continue;
			}

			$typeCounts = [
				'emergency' => $addonCounts[$addonId]['emergency_count'],
				'alert' => $addonCounts[$addonId]['alert_count'],
				'critical' => $addonCounts[$addonId]['critical_count'],
				'error' => $addonCounts[$addonId]['error_count'],
				'warning' => $addonCounts[$addonId]['warning_count'],
				'notice' => $addonCounts[$addonId]['notice_count'],
				'info' => $addonCounts[$addonId]['info_count'],
				'debug' => $addonCounts[$addonId]['debug_count'],
			];

			if (isset($addons[$addonId]))
			{
				$result[$addonId] = [
					'addon' => $addons[$addonId],
					'log_count' => $addonCounts[$addonId]['log_count'],
					'latest_log_date' => $addonCounts[$addonId]['latest_log_date'],
					'type_counts' => $typeCounts,
				];
			}
			else
			{
				$result[$addonId] = [
					'addon_id' => $addonId,
					'addon' => null,
					'log_count' => $addonCounts[$addonId]['log_count'],
					'latest_log_date' => $addonCounts[$addonId]['latest_log_date'],
					'type_counts' => $typeCounts,
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
		return $this->db()->delete('xf_addon_log', 'addon_id = ?', $addonId);
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
		$deletedCount = $this->db()->fetchOne('SELECT COUNT(*) FROM xf_addon_log WHERE date < ?', $cutOff);

		$this->db()->delete('xf_addon_log', 'date < ?', $cutOff);

		if ($deletedCount > 0)
		{
			$cutOffDate = date('Y-m-d H:i:s', $cutOff);

			Logger::withAddonId('Sylphian/Library')->debug('Add-on logs pruned successfully', [
				'deleted_records' => $deletedCount,
				'cutoff_date' => $cutOffDate,
				'pruned_date' => date('Y-m-d H:i:s'),
			]);
		}
		else
		{
			Logger::withAddonId('Sylphian/Library')->debug('No add-on logs needed pruning');
		}
	}
}
