<?php

namespace Sylphian\Library\Install;

use XF\Entity\AbstractField;
use XF\Entity\ThreadField;
use XF\Entity\UserField;
use XF\PrintableException;

/**
 * Setup helpers for custom fields. They throw on failure and are safe to re-run, so a failed
 * install or upgrade step can simply be resumed.
 */
trait CustomFieldInstallTrait
{
	/**
	 * Creates the field and its phrases in one transaction. An existing field is left alone, apart
	 * from filling in a missing title or description phrase, unless $update is true.
	 *
	 * Defaults are applied only on create, so an update changes only the options it names.
	 *
	 * @template T of AbstractField
	 * @param class-string<T> $entityClass
	 * @return T
	 *
	 * @throws PrintableException when the field fails validation
	 */
	protected function ensureCustomField(
		string $entityClass,
		string $fieldId,
		string $title,
		string $description = '',
		array $options = [],
		bool $update = false
	): AbstractField
	{
		$em = \XF::em();

		/** @var T|null $field */
		$field = $em->find($entityClass, $fieldId);
		$isNew = !$field;

		if ($isNew)
		{
			$field = $em->create($entityClass);
			$field->field_id = $fieldId;
			$field->bulkSet(array_replace($this->getCustomFieldDefaults($entityClass), $options));
		}
		else if ($update)
		{
			$field->bulkSet($options);
		}

		$phrases = [];
		if ($isNew || $update || !$field->MasterTitle)
		{
			$phrases[] = [true, $title];
		}
		if ($isNew || $update || !$field->MasterDescription)
		{
			$phrases[] = [false, $description];
		}

		$saveField = $isNew || $update;
		if (!$saveField && !$phrases)
		{
			return $field;
		}

		// Validated before the transaction, so the admin sees XenForo's phrased error rather than a database one.
		if ($saveField && !$field->preSave())
		{
			throw new PrintableException($field->getErrors());
		}

		$db = $em->getDb();
		$db->beginTransaction();

		try
		{
			if ($saveField)
			{
				$field->saveIfChanged($saved, true, false);
			}

			foreach ($phrases AS [$isTitle, $text])
			{
				$phrase = $field->getMasterPhrase($isTitle);
				$phrase->phrase_text = $text;
				$phrase->global_cache = true;
				$phrase->save(true, false);

				// The relation was cached as empty before the phrase existed, and a later call in the
				// same request (a CLI install runs every step in one process) would create it twice.
				$field->hydrateRelation($isTitle ? 'MasterTitle' : 'MasterDescription', $phrase);
			}

			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollback();

			// Otherwise the entity manager still holds the rolled-back field, and a retry in the same
			// request would find it and skip the create.
			if ($isNew)
			{
				$em->detachEntity($field);
			}

			throw $e;
		}

		return $field;
	}

	/**
	 * @throws PrintableException
	 */
	protected function ensureUserField(string $fieldId, string $title, string $description = '', array $options = [], bool $update = false): UserField
	{
		return $this->ensureCustomField(UserField::class, $fieldId, $title, $description, $options, $update);
	}

	/**
	 * @throws PrintableException
	 */
	protected function ensureThreadField(string $fieldId, string $title, string $description = '', array $options = [], bool $update = false): ThreadField
	{
		return $this->ensureCustomField(ThreadField::class, $fieldId, $title, $description, $options, $update);
	}

	/**
	 * Fields that do not exist are skipped. Deleting a field also removes its phrases and its users' values.
	 *
	 * @param class-string<AbstractField> $entityClass
	 * @param list<string> $fieldIds
	 */
	protected function removeCustomFields(string $entityClass, array $fieldIds): void
	{
		foreach (\XF::finder($entityClass)->whereIds($fieldIds)->fetch() AS $field)
		{
			$field->delete();
		}
	}

	/**
	 * Private by default: a new user field is not shown on profiles or messages, or at registration.
	 *
	 * @param class-string<AbstractField> $entityClass
	 */
	protected function getCustomFieldDefaults(string $entityClass): array
	{
		$defaults = ['display_order' => 1, 'required' => false];

		if (is_a($entityClass, UserField::class, true))
		{
			$defaults += [
				'display_group' => 'preferences',
				'user_editable' => 'yes',
				'moderator_editable' => true,
				'show_registration' => false,
				'viewable_profile' => false,
				'viewable_message' => false,
			];
		}

		return $defaults;
	}
}
