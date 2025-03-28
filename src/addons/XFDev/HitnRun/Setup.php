<?php

namespace XFDev\HitnRun;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;


class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1()
    {
        $sm = $this->schemaManager();

        if($sm->tableExists('xftt_peer') && !$sm->columnExists('xftt_peer','hit'))
        {
            $sm->alterTable('xftt_peer',function(Alter $table){
                $table->addColumn('hit','enum',['no','yes']);
                # The following line is representing the status of checked torrent record
                # 0 means that the record is not checked yet
                # 1 means that the record is checked and the hit is set to yes
                # 2 means that the record is excluded from checking
                # 3 means that the hnr is removed using the zap of upload or seedbonus
                $table->addColumn('hnr_checked','int');
                $table->addColumn('hnr_last_checked','int')->comment('Last Time when hnr is checked against this peer');
                $table->addKey(['hit','hnr_checked'],'hit_hnr_checked');
                $table->addKey(['hit','user_id'],'hit_uid');
            });
        }

        if(!$sm->columnExists('xf_user_upgrade','xfdev_hnr_reset')) {
            $sm->alterTable('xf_user_upgrade', function (Alter $table) {
                $table->addColumn('xfdev_hnr_reset', 'int');
            });
        }
    }

    public function uninstall(array $stepParams = [])
    {
        $sm = $this->schemaManager();

        if($sm->tableExists('xftt_peer') && !$sm->columnExists('xftt_peer','hit'))
        {
            $sm->alterTable('xftt_peer',function(Alter $table){
                $table->dropColumns('hit');
                $table->dropColumns('hnr_checked');
                $table->dropColumns('hnr_last_checked');
                $table->dropIndexes(['hit_hnr_checked','hit_uid']);
            });
        }
    }

}