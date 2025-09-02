<?php

namespace Sylphian\Library\Cron;

use Sylphian\Library\Repository\LogRepository;

class LogCleanup
{
	public static function cleanUpCron(): void
	{
		/** @var LogRepository $logRepo */
		$logRepo = \XF::repository('Sylphian\Library:Log');
		$logRepo->pruneLogs();
	}
}
