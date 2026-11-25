<?php

namespace Sylphian\Library\Repository;

use Sylphian\Library\AddonPermissionHandler;
use Sylphian\Library\Logger\Logger;
use XF\Entity\AddOn;
use XF\Entity\User;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Repository\WebhookRepository;

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
		// The Finder cannot select DISTINCT values.
		$db = $this->db();
		$types = $db->fetchAllColumn('SELECT DISTINCT type FROM xf_addon_log WHERE addon_id = ? ORDER BY type', $addonId);
		$userIds = $db->fetchAllColumn('SELECT DISTINCT user_id FROM xf_addon_log WHERE addon_id = ?', $addonId);

		$users = [];
		if (in_array(null, $userIds, true))
		{
			$users['System'] = \XF::phrase('system');
		}

		$userIds = array_filter(array_map('intval', $userIds));
		if ($userIds)
		{
			foreach ($this->finder(User::class)->whereIds($userIds)->order('username')->fetch() AS $user)
			{
				$users[$user->user_id] = $user->username;
			}
		}

		return [
			'types' => array_combine($types, $types),
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
		$types = (array) ($filters['type'] ?? []);
		if ($types && !in_array('any', $types, true))
		{
			$finder->where('type', array_values(array_map('strval', $types)));
		}

		$userFilter = (array) ($filters['user_id'] ?? []);
		if ($userFilter && !in_array('0', $userFilter, true) && !in_array('any', $userFilter, true))
		{
			$userConditions = [];
			foreach ($userFilter AS $value)
			{
				if (strtolower((string) $value) === 'system')
				{
					$userConditions[] = ['user_id', null];
				}
				else if ($userId = (int) $value)
				{
					$userConditions[] = ['user_id', $userId];
				}
			}

			if ($userConditions)
			{
				$finder->whereOr($userConditions);
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
		$db = $this->db();
		$existing = $db->fetchRow('SELECT COUNT(*) AS log_count, MAX(log_id) AS last_log_id FROM xf_addon_log WHERE addon_id = ?', $addonId);
		$logCount = (int) $existing['log_count'];
		$lastLogId = (int) $existing['last_log_id'];

		if (!$logCount)
		{
			return 0;
		}

		/** @var AddOn $addon */
		$addon = \XF::em()->find('XF:AddOn', $addonId);
		$addonTitle = $addon ? $addon->title : $addonId;

		$payload = [
			'content' => \XF::app()->language(\XF::options()->defaultLanguageId)
				->renderPhrase('sylphian_library_batch_deleted_x_logs', ['count' => $logCount], 'raw'),
			'addon_info' => [
				'addon_id' => $addonId,
				'title' => $addonTitle,
				'version_string' => $addon ? $addon->version_string : '',
			],
			'date' => \XF::$time,
			'date_formatted' => \XF::language()->dateTime(\XF::$time),
			'log_count' => $logCount,
			'last_log_id' => $lastLogId,
			'user_id' => \XF::visitor()->user_id,
			'user_name' => \XF::visitor()->username,
		];

		$webhookRepo = $this->repository(WebhookRepository::class);
		$webhookRepo->queueWebhook(
			'syl_library_addon_log',
			$lastLogId,
			'batch_delete',
			$payload
		);

		// Keep entries logged since the count so the webhook's count stays exact.
		return $db->delete('xf_addon_log', 'addon_id = ? AND log_id <= ?', [$addonId, $lastLogId]);
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

	public function isPruningEnabled(): bool
	{
		return $this->getLogLength() !== 0;
	}

	/**
	 * @deprecated 1.2.0 Use isPruningEnabled().
	 */
	public function isEnabled(): bool
	{
		return $this->isPruningEnabled();
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
		if (!$this->isPruningEnabled())
		{
			return;
		}

		$cutOff = $cutOff ?? $this->getCutOff();
		$deleted = $this->db()->delete('xf_addon_log', 'date < ?', $cutOff);

		if ($deleted)
		{
			Logger::withAddonId('Sylphian/Library')->info('Pruned {count} add-on log entries', [
				'count' => $deleted,
				'cutoff_date' => date('Y-m-d H:i:s', $cutOff),
			]);
		}
	}
}
