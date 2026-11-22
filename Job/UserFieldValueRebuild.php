<?php

namespace Sylphian\Library\Job;

use Sylphian\Library\Logger\Logger;
use XF\Entity\UserField;
use XF\Job\AbstractJob;
use XF\Job\JobResult;
use XF\Repository\UserFieldRepository;
use XF\Util\Php;

class UserFieldValueRebuild extends AbstractJob
{
	protected $defaultData = [
		'fieldId' => null,
		'lastUserId' => 0,
		'processed'  => 0,
		'changed'    => 0,
		'removed'    => 0,
		'total'      => null,
		'batch'      => 100,
		'renameMap'  => [],
	];

	public function run($maxRunTime): JobResult
	{
		$start = microtime(true);
		$db = $this->app->db();

		$fieldId = (string) ($this->data['fieldId'] ?? '');
		if ($fieldId === '')
		{
			return $this->complete();
		}

		/** @var UserField|null $field */
		$field = $this->app->em()->find('XF:UserField', $fieldId);
		if (!$field)
		{
			Logger::withAddonId('Sylphian/Library')->warning('UserFieldValueRebuild: field not found', [
				'field_id' => $fieldId,
			]);
			return $this->complete();
		}

		if ($this->data['total'] === null)
		{
			$this->data['total'] = (int) $db->fetchOne(
				'SELECT COUNT(*) FROM xf_user_field_value WHERE field_id = ?',
				$fieldId
			);

			$batch = (int) $this->data['batch'];
			if ($batch < 1)
			{
				$batch = 1;
			}
			if ($batch > 100)
			{
				$batch = 100;
			}
			$this->data['batch'] = $batch;
		}

		$choices = (array) $field->field_choices;
		$allowedKeys = array_map('strval', array_keys($choices));
		$multiple = in_array($field->field_type, ['checkbox', 'multiselect'], true);
		$fieldRepo = $this->app->repository(UserFieldRepository::class);

		$max = (float) $maxRunTime;

		while (true)
		{
			if (microtime(true) - $start >= $max)
			{
				$this->saveIncrementalData();
				return $this->resume();
			}

			$rows = $db->fetchAll(
				$db->limit(
					'SELECT user_id, field_id, field_value
                     FROM xf_user_field_value
                     WHERE field_id = ? AND user_id > ?
                     ORDER BY user_id ',
					$this->data['batch']
				),
				[$fieldId, (int) $this->data['lastUserId']]
			);

			if (!$rows)
			{
				Logger::withAddonId('Sylphian/Library')->info('UserFieldValueRebuild: finished', [
					'field_id' => $fieldId,
					'processed' => $this->data['processed'],
					'changed' => $this->data['changed'],
					'removed' => $this->data['removed'],
				]);
				return $this->complete();
			}

			foreach ($rows AS $row)
			{
				$userId = (int) $row['user_id'];
				$newValue = $this->normalizeValue($row['field_value'], $allowedKeys, $multiple);

				if ($newValue !== $row['field_value'])
				{
					// xf_user_field_value has no entity, so it is written directly, as core does.
					if ($newValue === null)
					{
						$db->delete('xf_user_field_value', 'user_id = ? AND field_id = ?', [$userId, $fieldId]);
						$this->data['removed']++;
					}
					else
					{
						$db->update('xf_user_field_value', [
							'field_value' => $newValue,
						], 'user_id = ? AND field_id = ?', [$userId, $fieldId]);
						$this->data['changed']++;
					}

					$fieldRepo->rebuildUserFieldValuesCache($userId);
				}

				$this->data['lastUserId'] = $userId;
				$this->data['processed']++;

				if (microtime(true) - $start >= $max)
				{
					$this->saveIncrementalData();
					return $this->resume();
				}
			}
		}
	}

	public function getStatusMessage(): string
	{
		$processed = (int) $this->data['processed'];
		$total = (int) ($this->data['total'] ?? 0);
		$fieldId = (string) ($this->data['fieldId'] ?? '');

		if ($total > 0)
		{
			return sprintf('Rebuilding user field "%s" values... %d/%d', $fieldId, $processed, $total);
		}
		else
		{
			return sprintf('Rebuilding user field "%s" values...', $fieldId);
		}
	}

	public function canCancel(): true
	{
		return true;
	}

	public function canTriggerByChoice(): true
	{
		return true;
	}

	/**
	 * Applies the rename map and drops keys that are no longer choices. Checkbox and multiselect
	 * values are serialized arrays, as XenForo stores them.
	 *
	 * @param list<string> $allowedKeys
	 * @return string|null null when nothing valid is left and the row should be deleted
	 */
	protected function normalizeValue(string $raw, array $allowedKeys, bool $multiple): ?string
	{
		$renameMap = (array) $this->data['renameMap'];
		$map = static function (string $key) use ($renameMap): string
		{
			return array_key_exists($key, $renameMap) ? (string) $renameMap[$key] : $key;
		};

		if ($multiple)
		{
			$values = Php::safeUnserialize($raw);
			$values = is_array($values) ? array_map('strval', $values) : [];
			$kept = array_values(array_unique(array_intersect(array_map($map, $values), $allowedKeys)));

			return $kept ? serialize($kept) : null;
		}

		$value = $map(trim($raw));

		return in_array($value, $allowedKeys, true) ? $value : null;
	}
}
