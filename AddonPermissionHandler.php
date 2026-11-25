<?php

namespace Sylphian\Library;

use XF\Entity\AdminPermission;
use XF\Entity\User;

class AddonPermissionHandler
{
	/** @var array<string, string|null> add-on ID => the permission that guards its logs */
	protected static array $logPermissionIds = [];

	/**
	 * @var array<string, true>|null
	 * @deprecated 1.2.0 Only used by the sylLib_ naming rule, which will be removed in 1.3.0.
	 */
	protected static ?array $legacyPermissionIds = null;

	/**
	 * Checks if a user may view the logs of a specific add-on.
	 *
	 * Every admin needs viewAddonLogs. If the add-on declares a log permission through the
	 * sylphian_library_log_permission code event, that permission is required as well.
	 *
	 * @param User|null $user defaults to the visitor
	 * @param string $addonId e.g. "Vendor/Addon"
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

		if (!$user->hasAdminPermission('viewAddonLogs'))
		{
			return false;
		}

		$permissionId = self::getLogPermissionId($addonId);

		return $permissionId === null || $user->hasAdminPermission($permissionId);
	}

	/**
	 * The admin permission that guards an add-on's logs, or null when viewAddonLogs is enough.
	 *
	 * A declared permission that does not exist fails closed: no admin has it, so only super
	 * admins can see those logs.
	 */
	public static function getLogPermissionId(string $addonId): ?string
	{
		if (!array_key_exists($addonId, self::$logPermissionIds))
		{
			$permissionId = null;
			\XF::fire('sylphian_library_log_permission', [$addonId, &$permissionId], $addonId);

			self::$logPermissionIds[$addonId] = ($permissionId !== null && $permissionId !== '')
				? $permissionId
				: self::getLegacyLogPermissionId($addonId);
		}

		return self::$logPermissionIds[$addonId];
	}

	/**
	 * The permission named by the sylLib_<vendor>_<addon> rule, when one with that name exists.
	 *
	 * @deprecated 1.2.0 Declare the permission with the sylphian_library_log_permission code event
	 *     instead. This fallback will be removed in 1.3.0.
	 */
	protected static function getLegacyLogPermissionId(string $addonId): ?string
	{
		$permissionId = 'sylLib_' . self::formatAddonIdForPermission($addonId);

		return self::adminPermissionExists($permissionId) ? $permissionId : null;
	}

	/**
	 * Formats an add-on ID for the sylLib_ naming rule: "Vendor/Addon" becomes "vendor_addon".
	 *
	 * @deprecated 1.2.0 The naming rule is replaced by the sylphian_library_log_permission code
	 *     event. This method will be removed in 1.3.0.
	 */
	public static function formatAddonIdForPermission(string $addonId): string
	{
		$value = strtolower(str_replace('/', '_', $addonId));
		return mb_strcut($value, 0, 25, 'UTF-8');
	}

	/**
	 * Checks if a sylLib_ admin permission exists. They are loaded once per request, because the
	 * cards page and the dashboard check one add-on after another.
	 *
	 * @deprecated 1.2.0 Only used by the sylLib_ naming rule. Use getLogPermissionId() to find an
	 *     add-on's log permission. This method will be removed in 1.3.0.
	 */
	public static function adminPermissionExists(string $permissionId): bool
	{
		if (self::$legacyPermissionIds === null)
		{
			$finder = \XF::finder(AdminPermission::class);
			$rows = $finder
				->where('admin_permission_id', 'LIKE', $finder->escapeLike('sylLib_', '?%'))
				->fetchColumns('admin_permission_id');

			self::$legacyPermissionIds = array_fill_keys(array_column($rows, 'admin_permission_id'), true);
		}

		return isset(self::$legacyPermissionIds[$permissionId]);
	}
}
