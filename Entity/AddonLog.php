<?php

namespace Sylphian\Library\Entity;

use XF\Entity\AddOn;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $log_id
 * @property string $addon_id
 * @property int $date
 * @property string $type
 * @property string $content
 * @property int|null $user_id
 * @property array|null $details
 *
 * RELATIONS
 * @property-read User|null $User
 * @property-read AddOn|null $AddOn
 */
class AddonLog extends Entity
{
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_addon_log';
		$structure->shortName = 'Sylphian\Library:AddonLog';
		$structure->primaryKey = 'log_id';
		$structure->columns = [
			'log_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'required' => true],
			'date' => ['type' => self::UINT, 'default' => \XF::$time],
			'type' => ['type' => self::STR, 'maxLength' => 20, 'required' => true],
			'content' => ['type' => self::STR, 'required' => true],
			'user_id' => ['type' => self::UINT, 'nullable' => true, 'default' => \XF::visitor()->user_id],
			'details' => ['type' => self::JSON_ARRAY, 'nullable' => true],
		];

		$structure->getters = [];

		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
			'AddOn' => [
				'entity' => 'XF:AddOn',
				'type' => self::TO_ONE,
				'conditions' => 'addon_id',
				'primary' => true,
			],
		];

		return $structure;
	}

	/**
	 * Validate that the addon exists in the system
	 */
	protected function _preSave(): void
	{
		if ($this->isChanged('addon_id'))
		{
			$addon = $this->em()->find('XF:AddOn', $this->addon_id);
			if (!$addon)
			{
				$this->error(\XF::phrase('addon_with_id_not_found', ['id' => $this->addon_id]));
			}
		}
	}
}
