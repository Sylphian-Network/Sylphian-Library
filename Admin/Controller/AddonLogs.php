<?php

namespace Sylphian\Library\Admin\Controller;

use Sylphian\Library\AddonPermissionHandler;
use Sylphian\Library\Entity\AddonLog;
use Sylphian\Library\Logger\Logger;
use Sylphian\Library\Repository\LogRepository;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Message;
use XF\Mvc\Reply\View;
use XF\PrintableException;

class AddonLogs extends AbstractController
{
	/**
	 * Display all unique addons from the xf_addon_log table as cards
	 */
	public function actionIndex(): View
	{
		/** @var LogRepository $logRepo */
		$logRepo = $this->repository('Sylphian\Library:Log');

		$addons = $logRepo->getUniqueAddonsWithCounts();

		$viewParams = [
			'addons' => $addons,
		];

		return $this->view('Sylphian\Library:AddonCards', 'sylphian_library_addon_cards', $viewParams);
	}

	/**
	 * Display logs for a specific addon
	 */
	public function actionAddon(ParameterBag $params): View|AbstractReply
	{
		$addonId = $this->filter('addon_id', 'str');
		if (!$addonId)
		{
			$addonId = $params->get('addon_id');
		}

		if (!$addonId)
		{
			return $this->notFound();
		}

		if (!AddonPermissionHandler::canViewAddonLogs(null, $addonId))
		{
			return $this->noPermission();
		}

		$page = $this->filterPage();
		$perPage = 20;

		$filters = [
			'start_date' => $this->filter('start_date', 'datetime'),
			'end_date' => $this->filter('end_date', 'datetime'),
			'type' => $this->filter('type', 'array'),
			'user_id' => $this->filter('user_id', 'array'),
		];

		/** @var LogRepository $logRepo */
		$logRepo = $this->repository('Sylphian\Library:Log');

		$filterBits = $logRepo->getAllAddonLogTypesAndUsers($addonId);

		$filtersApplied = !empty($filters['start_date']) ||
			!empty($filters['end_date']) ||
			(!empty($filters['type']) && !(is_array($filters['type']) && in_array('any', $filters['type']))) ||
			(!empty($filters['user_id']) && !(is_array($filters['user_id']) && (in_array('0', $filters['user_id']) || in_array('any', $filters['user_id']))));

		if ($filtersApplied)
		{
			$logs = $logRepo->getLogsForAddon($addonId, $page, $perPage, $filters);
			$total = $logRepo->getLogCountForAddon($addonId, $filters);
		}
		else
		{
			$logs = $logRepo->getLogsForAddon($addonId, $page, $perPage);
			$total = $logRepo->getLogCountForAddon($addonId);

		}

		$addon = $this->em()->find('XF:AddOn', $addonId);

		$viewParams = [
			'logs' => $logs,
			'addon' => $addon,
			'addonId' => $addonId,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'filterBits' => $filterBits,
			'filters' => $filters,
		];

		Logger::debug('addon filtering results', $viewParams);

		return $this->view('Sylphian\Library:AddonLogs', 'sylphian_addon_logs', $viewParams);
	}

	/**
	 * Display details for a specific log entry
	 */
	public function actionDetails(): View|AbstractReply|Message
	{
		$logId = $this->filter('log_id', 'uint');
		if (!$logId)
		{
			return $this->notFound();
		}

		/** @var AddonLog $log */
		$log = $this->em()->find('Sylphian\Library:AddonLog', $logId);
		if (!$log)
		{
			return $this->notFound();
		}

		if (!AddonPermissionHandler::canViewAddonLogs(null, $log->addon_id))
		{
			return $this->noPermission();
		}

		$formattedDetails = $log->details ? json_encode($log->details, JSON_PRETTY_PRINT) : '';

		$viewParams = [
			'log' => $log,
			'formattedDetails' => $formattedDetails,
		];

		return $this->view('Sylphian\Library:LogDetails', 'sylphian_library_log_details', $viewParams);
	}

	/**
	 * Delete a specific log entry
	 * @throws PrintableException
	 */
	public function actionDelete(): View|AbstractReply
	{
		$logId = $this->filter('log_id', 'uint');
		if (!$logId)
		{
			return $this->notFound();
		}

		/** @var AddonLog $log */
		$log = $this->em()->find('Sylphian\Library:AddonLog', $logId);
		if (!$log)
		{
			return $this->notFound();
		}

		if (!AddonPermissionHandler::canViewAddonLogs(null, $log->addon_id))
		{
			return $this->noPermission();
		}

		$formattedDetails = $log->details ? json_encode($log->details, JSON_PRETTY_PRINT) : '';

		if ($this->isPost())
		{
			$log->delete();
			return $this->redirect($this->buildLink('logs/addon_logs/view', null, ['addon_id' => $log->addon_id]));
		}

		$viewParams = [
			'log' => $log,
			'formattedDetails' => $formattedDetails,
		];

		return $this->view('Sylphian\Library:LogDelete', 'sylphian_library_log_delete', $viewParams);
	}

	public function actionClear(): AbstractReply
	{
		$addonId = $this->filter('addon_id', 'str');
		if (!$addonId)
		{
			return $this->notFound();
		}

		if (!AddonPermissionHandler::canViewAddonLogs(null, $addonId))
		{
			return $this->noPermission();
		}

		$addon = $this->em()->find('XF:AddOn', $addonId);

		if ($this->isPost())
		{
			/** @var LogRepository $logRepo */
			$logRepo = $this->repository('Sylphian\Library:Log');

			$logRepo->clearLogsForAddon($addonId);

			return $this->redirect($this->buildLink('logs/addon_logs/view', null, ['addon_id' => $addonId]));
		}

		$viewParams = [
			'addon' => $addon,
			'addonId' => $addonId,
		];

		return $this->view('Sylphian\Library:LogClear', 'sylphian_library_logs_clear', $viewParams);
	}
}
