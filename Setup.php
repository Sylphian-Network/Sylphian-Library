<?php

namespace Sylphian\Library;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1(): void
	{
		$sm = $this->schemaManager();

		if ($sm->tableExists('xf_addon_log'))
		{
			return;
		}

		$sm->createTable('xf_addon_log', function (Create $table)
		{
			$table->addColumn('log_id', 'int')->autoIncrement();
			$table->addColumn('addon_id', 'varbinary', 50);
			$table->addColumn('date', 'int');
			$table->addColumn('type', 'varchar', 20);
			$table->addColumn('content', 'text');
			$table->addColumn('user_id', 'int')->nullable();
			$table->addColumn('details', 'blob')->nullable();

			$table->addKey('date');
			$table->addKey('addon_id');
			$table->addKey(['addon_id', 'type']);
		});
	}

	public function uninstallStep1(): true
	{
		$this->schemaManager()->dropTable('xf_addon_log');

		return true;
	}
}
