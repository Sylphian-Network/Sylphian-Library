<?php

namespace Sylphian\Library\Logger;

trait LoggableTrait
{
	/**
	 * Cached logger instance
	 * @var AddonLogger|null
	 */
	protected ?AddonLogger $logger = null;

	/**
	 * Get the logger instance for this class
	 *
	 * @param string|null $addonId Optional addon ID, will be automatically detected if not provided
	 * @return AddonLogger
	 */
	protected function getLogger(?string $addonId = null): AddonLogger
	{
		if ($this->logger === null)
		{
			$this->logger = new AddonLogger(\XF::em(), $addonId);
		}

		return $this->logger;
	}
}
