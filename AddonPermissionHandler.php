<?php

namespace Sylphian\Library;

use XF\Entity\User;

class AddonPermissionHandler
{
	/**
	 * Checks if a user has permission to access the logs of a specific addon
	 *
	 * Permission format: sylLib_vendor_addon
	 * For example: sylLib_sylphian_map
	 *
	 * @param User|null $user The user to check permissions for (defaults to current visitor)
	 * @param string $addonId The addon identifier (e.g. "Vendor/Addon")
	 * @return bool Whether the user has permission to access logs
	 */
	public static function canViewAddonLogs(?User $user = null, string $addonId = ''): bool
	{
		if (!$user)
		{
			$user = \XF::visitor();
		}

		if ($user->is_super_admin)
		{
			return true;
		}

		$formattedAddonId = self::formatAddonIdForPermission($addonId);

		$permissionId = 'sylLib_' . $formattedAddonId;

		if (!self::adminPermissionExists($permissionId))
		{
			return true;
		}

		return $user->hasAdminPermission($permissionId);
	}

	/**
	 * Formats addon ID to permission format
	 * e.g. "Vendor/Addon" becomes "vendor_addon"
	 *
	 * @param string $addonId
	 * @return string
	 */
	public static function formatAddonIdForPermission(string $addonId): string
	{
		$value = strtolower(str_replace('/', '_', $addonId));
		return mb_strcut($value, 0, 25, 'UTF-8');
	}

	/**
	 * Checks if an admin permission exists in the system
	 *
	 * @param string $permissionId
	 * @return bool
	 */
	public static function adminPermissionExists(string $permissionId): bool
	{
		return \XF::finder('XF:AdminPermission')
				->where('admin_permission_id', $permissionId)
				->total() > 0;
	}
}
