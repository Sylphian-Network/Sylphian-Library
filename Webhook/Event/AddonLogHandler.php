<?php

namespace Sylphian\Library\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;

class AddonLogHandler extends AbstractHandler
{
	/**
	 * Define the available events for addon logs
	 *
	 * @return array
	 */
	public function getEvents(): array
	{
		return [
			'debug',
			'info',
			'notice',
			'warning',
			'error',
			'critical',
			'alert',
			'emergency',
		];
	}

	/**
	 * Provide a hint for each event
	 *
	 * @param string $event
	 * @return string
	 */
	public function getEventHint(string $event): string
	{
		return match ($event)
		{
			'debug' => 'When a debug level log is created',
			'info' => 'When an info level log is created',
			'notice' => 'When a notice level log is created',
			'warning' => 'When a warning level log is created',
			'error' => 'When an error level log is created',
			'critical' => 'When a critical level log is created',
			'alert' => 'When an alert level log is created',
			'emergency' => 'When an emergency level log is created',
			default => '',
		};
	}

	/**
	 * Get relations to include with the entity when sending webhooks
	 *
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['AddOn'];
	}
}
