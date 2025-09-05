<?php

namespace Sylphian\Library;

use Sylphian\Library\Logger\AddonLogger;
use XF\App;
use XF\Container;

class AppSetup
{
	/**
	 * Register services in the dependency injection container
	 *
	 * @param App $app The XenForo application instance
	 */
	public static function appSetup(App $app): void
	{
		$app->container()->factory('addonLogger', function (?string $addonId, array $params, Container $container)
		{
			$loggerClass = \XF::extendClass(AddonLogger::class);
			return new $loggerClass($container['em'], $addonId);
		});
	}
}
