<?php

namespace Sylphian\Library\Repository;

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
		}

		/** @var \XF\Repository\UserFieldRepository $repo */
		$repo = \XF::repository('XF:UserField');
		$repo->rebuildFieldCache();

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
}
