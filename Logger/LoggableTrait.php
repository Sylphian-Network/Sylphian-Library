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

    /**
     * Logs an error message and returns an Error reply
     *
     * @param \Stringable|string $error The error message to log and return
     * @param array $context Additional context data for the log entry
     * @param int $code HTTP response code
     *
     * @return \XF\Mvc\Reply\Error
     */
    protected function loggedError(\Stringable|string $error, array $context = [], int $code = 200): \XF\Mvc\Reply\Error
    {
        $logger = $this->getLogger();
        $logger->error((string)$error, $context);

        return $this->error($error, $code);
    }
}
