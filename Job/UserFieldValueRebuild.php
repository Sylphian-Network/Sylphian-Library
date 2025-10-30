<?php

namespace Sylphian\Library\Job;

use Sylphian\Library\Logger\Logger;
use XF\Entity\UserField;
use XF\Job\AbstractJob;
use XF\Job\JobResult;

class UserFieldValueRebuild extends AbstractJob
{
	protected $defaultData = [
		'fieldId' => null,
		'lastUserId' => 0,
		'processed'  => 0,
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

			Logger::withAddonId('Sylphian/Library')->info('UserFieldValueRebuild: snapshot acquired', [
				'field_id' => $fieldId,
				'total' => $this->data['total'],
			]);

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
		$allowedSingle = array_map('strval', array_keys($choices));

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
					'total' => $this->data['total'],
				]);
				return $this->complete();
			}

			foreach ($rows AS $row)
			{
				$userId = (int) $row['user_id'];
				$raw = $row['field_value'];

				$newValue = $this->normalizeValue($raw, $allowedSingle);

				if ($newValue !== $raw)
				{
					$db->update('xf_user_field_value', [
						'field_value' => $newValue,
					], 'user_id = ? AND field_id = ?', [$userId, $fieldId]);

					Logger::withAddonId('Sylphian/Library')->notice('UserFieldValueRebuild: corrected field value', [
						'field_id' => $fieldId,
						'user_id' => $userId,
						'before' => $raw,
						'after' => $newValue,
					]);
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
	 * @param string $raw
	 * @param array $allowedKeys
	 * @return string
	 */
	protected function normalizeValue(string $raw, array $allowedKeys): string
	{
		$trimmed = trim($raw);
		if ($trimmed === '')
		{
			return '';
		}

		$renameMap = (array) ($this->data['renameMap'] ?? []);
		$applyMap = static function (string $k) use ($renameMap): string
		{
			return array_key_exists($k, $renameMap) ? (string) $renameMap[$k] : $k;
		};

		$decoded = json_decode($trimmed, true);
		if (is_array($decoded))
		{
			$mapped = [];
			foreach ($decoded AS $v)
			{
				$mapped[] = $applyMap((string) $v);
			}
			$filtered = [];
			foreach ($mapped AS $s)
			{
				if (in_array($s, $allowedKeys, true))
				{
					$filtered[] = $s;
				}
			}
			return $filtered ? json_encode(array_values(array_unique($filtered))) : '';
		}

		if (str_contains($trimmed, ','))
		{
			$parts = array_map('trim', explode(',', $trimmed));
			$mapped = array_map($applyMap, array_map('strval', $parts));
			$filtered = [];
			foreach ($mapped AS $s)
			{
				if ($s !== '' && in_array($s, $allowedKeys, true))
				{
					$filtered[] = $s;
				}
			}
			return $filtered ? json_encode(array_values(array_unique($filtered))) : '';
		}

		$scalar = $applyMap($trimmed);
		if (in_array($scalar, $allowedKeys, true))
		{
			return $scalar;
		}

		return '';
	}
}
