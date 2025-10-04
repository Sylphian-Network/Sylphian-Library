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
			'insert',
			'update',
			'delete',
			'batch_delete',
			'debug_create',
			'info_create',
			'notice_create',
			'warning_create',
			'error_create',
			'critical_create',
			'alert_create',
			'emergency_create',
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
			'insert' => 'When a new addon log is created',
			'update' => 'When an existing addon log is updated',
			'delete' => 'When an addon log is deleted',
			'batch_delete' => 'When multiple logs are deleted in a batch operation',
			'debug_create' => 'When a debug level log is created',
			'info_create' => 'When an info level log is created',
			'notice_create' => 'When a notice level log is created',
			'warning_create' => 'When a warning level log is created',
			'error_create' => 'When an error level log is created',
			'critical_create' => 'When a critical level log is created',
			'alert_create' => 'When an alert level log is created',
			'emergency_create' => 'When an emergency level log is created',
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
