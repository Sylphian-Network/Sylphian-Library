<?php

namespace Sylphian\Library\XF\Admin\Controller;

use Sylphian\Library\Repository\LogRepository;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View;

/**
 * Class extension for the IndexController to add library error log checking.
 */
class IndexController extends XFCP_IndexController
{
	/**
	 * @return View|AbstractReply
	 */
	public function actionIndex()
	{
		$reply = parent::actionIndex();

		if ($reply instanceof View)
		{
			$visitor = \XF::visitor();
			if ($visitor->hasAdminPermission('viewLogs'))
			{
				/** @var LogRepository $logRepo */
				$logRepo = $this->repository('Sylphian\Library:Log');

                $logCounts = $logRepo->getHighPriorityLogCounts();

                $viewParams = $reply->getParams();
                $viewParams['addonErrorLogs'] = (bool)$logCounts;
                $viewParams['addonErrorLogCounts'] = $logCounts;
                $reply->setParams($viewParams);
			}
		}

		return $reply;
	}
}
