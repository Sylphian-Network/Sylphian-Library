<?php

namespace Sylphian\Library\Cron;

use Sylphian\Library\Repository\LogRepository;

class LogCleanup
{
	public static function cleanUpCron(): void
	{
		$logRepo = \XF::repository(LogRepository::class);
		$logRepo->pruneLogs();
	}
}
