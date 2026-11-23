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
		if (!in_array($event, $this->getEvents(), true))
		{
			return '';
		}

		return \XF::phrase('sylphian_library_webhook_hint_' . $event)->render();
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
