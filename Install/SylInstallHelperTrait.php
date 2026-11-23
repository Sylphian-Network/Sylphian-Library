<?php

namespace Sylphian\Library\Install;

use Sylphian\Library\Logger\AddonLogger;
use Sylphian\Library\Logger\Logger;
use XF\Entity\UserField;

/**
 * Failures are logged under the add-on being installed and reported as false, never thrown.
 *
 * @deprecated 1.2.0 Use CustomFieldInstallTrait::ensureUserField(), which throws and can update a field.
 */
trait SylInstallHelperTrait
{
	use CustomFieldInstallTrait;

	/**
	 * An existing field counts as success, so a resumed install step can run it again.
	 */
	public function createUserField(string $fieldId, string $title, string $description = '', array $fieldOptions = []): bool
	{
		try
		{
			$this->ensureUserField($fieldId, $title, $description, array_replace(self::legacyUserFieldDefaults(), $fieldOptions));

			return true;
		}
		catch (\Throwable $e)
		{
			$this->legacyLogger()->error('Could not create user field {field_id}', [
				'field_id' => $fieldId,
				'exception' => $e,
			]);

			return false;
		}
	}

	public function removeUserField(string $fieldId): bool
	{
		try
		{
			$this->removeCustomFields(UserField::class, [$fieldId]);

			return true;
		}
		catch (\Throwable $e)
		{
			$this->legacyLogger()->error('Could not remove user field {field_id}', [
				'field_id' => $fieldId,
				'exception' => $e,
			]);

			return false;
		}
	}

	/**
	 * Logs under the add-on being installed. Outside an AbstractSetup there is no $this->addOn, so
	 * the add-on is found from the call stack instead.
	 */
	private function legacyLogger(): AddonLogger
	{
		return isset($this->addOn) ? Logger::withAddonId($this->addOn->getAddOnId()) : AddonLogger::create();
	}

	/**
	 * The defaults this trait has always used, which differ from CustomFieldInstallTrait's private ones.
	 */
	private static function legacyUserFieldDefaults(): array
	{
		return ['display_group' => 'personal', 'viewable_profile' => true];
	}
}
