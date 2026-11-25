<?php

namespace Sylphian\Library\Listener;

/**
 * The Library's own answers to sylphian_library_log_permission. Each method is registered with
 * the add-on ID it answers for as its event hint.
 */
class LogPermission
{
	public static function xf(string $addonId, ?string &$permissionId): void
	{
		$permissionId = 'sylLib_xf';
	}

	public static function library(string $addonId, ?string &$permissionId): void
	{
		$permissionId = 'sylLib_sylphian_library';
	}
}
