<?php

namespace Sylphian\Library\Repository;

use Sylphian\Library\Job\UserFieldValueRebuild;
use Sylphian\Library\Logger\Logger;
use XF\Entity\UserField;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class UserFieldRepository extends Repository
{
	/**
	 * Update a user field's choices if there is a real change.
	 *
	 * @param string $fieldId The XF user field id.
	 * @param array  $newChoices Associative array: value => label
	 *
	 * @return array{updated:bool,before:array,after:array,missingField?:bool}
	 */
	public function updateChoicesIfChanged(string $fieldId, array $newChoices): array
	{
		/** @var UserField|null $field */
		$field = $this->em->find('XF:UserField', $fieldId);
		if (!$field)
		{
			return [
				'updated' => false,
				'before'  => [],
				'after'   => [],
				'missingField' => true,
			];
		}

		$current = (array) $field->field_choices;
		$normalizedCurrent = $this->normalizeChoices($current);
		$normalizedNew     = $this->normalizeChoices($newChoices);

		if ($normalizedCurrent === $normalizedNew)
		{
			return [
				'updated' => false,
				'before'  => $current,
				'after'   => $current,
			];
		}

		$field->field_choices = $newChoices;

		try
		{
			$field->save();
		}
		catch (PrintableException|\Exception $e)
		{
			Logger::withAddonId('Sylphian/Library')->error('Error saving User Field options', [
				'field_id' => $fieldId,
				'exception' => $e->getMessage(),
			]);

			return [
				'updated' => false,
				'before'  => $current,
				'after'   => $current,
			];
		}

		/** @var \XF\Repository\UserFieldRepository $repo */
		$repo = \XF::repository('XF:UserField');
		$repo->rebuildFieldCache();

		$renameMap = $this->buildRenameMap($current, $newChoices);

		$jobManager = \XF::app()->jobManager();
		$uniqueId = 'sylUserFieldValueRebuild_' . $fieldId;
		$jobManager->enqueueUnique(
			$uniqueId,
			UserFieldValueRebuild::class,
			[
				'fieldId'   => $fieldId,
				'renameMap' => $renameMap,
			],
			false
		);

		Logger::withAddonId('Sylphian/Library')->info('Queued UserFieldValueRebuild job after choice update', [
			'field_id'   => $fieldId,
			'job_unique' => $uniqueId,
			'rename_map' => $renameMap,
		]);

		return [
			'updated' => true,
			'before'  => $current,
			'after'   => $newChoices,
		];
	}

	protected function normalizeChoices(array $choices): array
	{
		$out = [];
		foreach ($choices AS $value => $label)
		{
			$out[(string) $value] = trim((string) $label);
		}
		ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
		return $out;
	}

	/**
	 * @param array $oldChoices value => label
	 * @param array $newChoices value => label
	 * @return array<string,string> oldKey => newKey
	 */
	protected function buildRenameMap(array $oldChoices, array $newChoices): array
	{
		$old = $this->normalizeChoices($oldChoices);
		$new = $this->normalizeChoices($newChoices);

		$norm = static function (string $label): string
		{
			return strtolower(preg_replace('/\s+/', ' ', trim($label)));
		};

		$labelToOld = [];
		foreach ($old AS $k => $label)
		{
			$labelToOld[$norm($label)][] = (string) $k;
		}

		$labelToNew = [];
		foreach ($new AS $k => $label)
		{
			$labelToNew[$norm($label)][] = (string) $k;
		}

		$map = [];
		foreach ($labelToOld AS $nLabel => $oldKeys)
		{
			if (!isset($labelToNew[$nLabel]))
			{
				continue;
			}
			$newKeys = $labelToNew[$nLabel];
			if (count($oldKeys) === 1 && count($newKeys) === 1)
			{
				if ($oldKeys[0] !== $newKeys[0])
				{
					$map[$oldKeys[0]] = $newKeys[0];
				}
			}
		}

		return $map;
	}
}
