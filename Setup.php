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

	public function installStep1(): bool
	{
		try
		{
			$this->schemaManager()->createTable('xf_addon_log', function (Create $table)
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

			return true;
		}
		catch (\Exception $exception)
		{
			\XF::logException($exception, false, 'Error creating addon log table: ');
			return false;
		}
	}

	public function uninstallStep1(): true
	{
		$this->schemaManager()->dropTable('xf_addon_log');

		return true;
	}
}
