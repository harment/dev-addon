<?php

namespace TorrentTracker;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Exception;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

    // ################################ INSTALL ####################

    /**
     * Create Tables Required by Addon
     */
    public function installStep1()
    {
        $sm = $this->schemaManager();

    	if(!$sm->tableExists('xftt_announce_log')) {
            $sm->createTable('xftt_announce_log', function (Create $table) {
                $table->addColumn('id', 'int')->autoIncrement();
                $table->addColumn('ipa', 'int');
                $table->addColumn('port', 'int');
                $table->addColumn('event', 'int');
                $table->addColumn('info_hash', 'binary', 20);
                $table->addColumn('peer_id', 'binary', 20);
                $table->addColumn('downloaded', 'bigint', 20);
                $table->addColumn('left0', 'bigint', 20);
                $table->addColumn('uploaded', 'bigint', 20);
                $table->addColumn('uid', 'int');
                $table->addColumn('mtime', 'int');
                $table->addPrimaryKey('id');
            });
        }

        if(!$sm->tableExists('xftt_cheat_log')) {
            $sm->createTable('xftt_cheat_log', function (Create $table) {
                $table->addColumn('log_id', 'int')->autoIncrement();
                $table->addColumn('user_id', 'int');
                $table->addColumn('ipa', 'int');
                $table->addColumn('peer_id', 'binary', 20);
                $table->addColumn('upspeed', 'bigint', 20);
                $table->addColumn('tstamp', 'int');
                $table->addColumn('uploaded', 'bigint', 20);
                $table->addColumn('useragent', 'varchar', 30);
                $table->addPrimaryKey('log_id');
                $table->addKey(['user_id', 'tstamp'], 'uid');
                $table->addkey('tstamp', 'tstamp');
            });
        }

        if(!$sm->tableExists('xftt_config')) {
            $sm->createTable('xftt_config', function (Create $table) {
                $table->addColumn('name', 'varchar', 50);
                $table->addColumn('value', 'varchar', 255);
                $table->addPrimaryKey('name');
            });
        }

        if(!$sm->tableExists('xftt_deny_from_clients')) {
            $sm->createTable('xftt_deny_from_clients', function (Create $table) {
                $table->addColumn('peer_id', 'char', 20);
                $table->addColumn('comment', 'varchar', 200);
                $table->addPrimaryKey('peer_id');
            });
        }

        if(!$sm->tableExists('xftt_deny_from_hosts')) {
            $sm->createTable('xftt_deny_from_hosts', function (Create $table) {
                $table->addColumn('begin', 'int');
                $table->addColumn('end', 'int');
                $table->addColumn('comment', 'varchar', 200);
            });
        }

        if(!$sm->tableExists('xftt_torrent')) {
            $sm->createTable('xftt_torrent', function (Create $table) {
                $table->addColumn('torrent_id', 'int')->comment('Attachement id from xf_attachement');
                $table->addColumn('info_hash', 'binary', 20);
                $table->addColumn('user_id', 'int')->setDefault(0);
                $table->addColumn('thread_id', 'int')->setDefault(0);
                $table->addColumn('category_id', 'int')->setDefault(0);
                $table->addColumn('freeleech', 'tinyint')->setDefault(0);
                $table->addColumn('sticky', 'tinyint')->setDefault(0);
                $table->addColumn('flags', 'tinyint')->setDefault(0);
                $table->addColumn('leechers', 'int')->setDefault(0);
                $table->addColumn('seeders', 'int')->setDefault(0);
                $table->addColumn('completed', 'int')->setDefault(0);
                $table->addColumn('mtime', 'int')->setDefault(0)->Comment('last update time');
                $table->addColumn('ctime', 'int')->setDefault(0)->Comment('torrent creation time');
                $table->addColumn('size', 'bigint')->setDefault(0);
                $table->addColumn('number_files', 'int')->setDefault(0);
                $table->addColumn('balance', 'bigint')->setDefault(0);
                $table->addColumn('last_reseed_request', 'int')->setDefault(0);
                $table->addColumn('upload_multiplier','int')->setDefault(1);
                $table->addColumn('download_multiplier','int')->setDefault(1);
                $table->addPrimaryKey('torrent_id');
                $table->addUniqueKey('info_hash', 'info_hash');
                $table->addKey('freeleech', 'freeleech');
                $table->addKey('thread_id', 'thread_id');
                $table->addKey(['category_id', 'ctime'], 'category_time');
                $table->addKey('ctime', 'ctime');
                $table->addKey(['user_id', 'ctime'], 'user_time');

            });
        }

        if(!$sm->tableExists('xftt_torrent_info')) {
            $sm->createTable('xftt_torrent_info', function (Create $table) {
                $table->addColumn('torrent_id', 'int');
                $table->addColumn('file_details', 'mediumtext');
                $table->addPrimaryKey('torrent_id');
            });
        }

        if(!$sm->tableExists('xftt_peer')) {
            $sm->createTable('xftt_peer', function (Create $table) {
                $table->addColumn('id', 'int')->autoIncrement();
                $table->addColumn('torrent_id', 'int');
                $table->addColumn('user_id', 'int');
                $table->addColumn('active', 'tinyint');
                $table->addColumn('announced', 'int');
                $table->addColumn('completed', 'int');
                $table->addColumn('downloaded', 'bigint');
                $table->addColumn('uploaded', 'bigint');
                $table->addColumn('corrupt', 'bigint')->setDefault(0);
                $table->addColumn('left', 'bigint');
                $table->addColumn('leechtime', 'int')->setDefault(0);
                $table->addColumn('seedtime', 'int')->setDefault(0);
                $table->addColumn('mtime', 'int')->Comment('last announced');
                $table->addColumn('down_rate', 'bigint')->setDefault(0);
                $table->addColumn('up_rate', 'bigint')->setDefault(0);
                $table->addColumn('useragent', 'varchar', 30)->setDefault('');
                $table->addColumn('peer_id', 'binary', 20);
                $table->addColumn('ipa', 'int')->setDefault(0);
                $table->addUniqueKey(['torrent_id', 'user_id'], 'torrent_uid');
                $table->addKey('user_id', 'user_id');
            });
        }

        if(!$sm->tableExists('xftt_freeleech_request')) {
            $sm->createTable('xftt_freeleech_request', function (Create $table) {
                $table->addColumn('request_id', 'int')->autoIncrement();
                $table->addColumn('user_id', 'int')->setDefault(0);
                $table->addColumn('torrent_id', 'int');
                $table->addColumn('open', 'tinyint')->setDefault(1);
                $table->addColumn('action', 'enum', ['accept', 'reject', ''])->setDefault('');
                $table->addColumn('date', 'int')->setDefault(0);
                $table->addPrimaryKey('request_id');
                $table->addUniqueKey('torrent_id', 'torrent_id');
                $table->addKey('date', 'date');
            });
        }

        if(!$sm->tableExists('xftt_log')) {
            $sm->createTable('xftt_log', function (Create $table) {
                $table->addColumn('log_id', 'int')->autoIncrement();
                $table->addColumn('log_date', 'int');
                $table->addColumn('message', 'text');
                $table->addColumn('params', 'text');
                $table->addColumn('action', 'varchar', 50);
                $table->addColumn('is_error', 'tinyint')->setDefault(0);
                $table->addPrimaryKey('log_id');
                $table->addKey('log_date', 'log_date');
            });
        }

        if(!$sm->tableExists('xftt_scrap_log')) {
            $sm->createTable('xftt_scrap_log', function (Create $table) {
                $table->addColumn('id', 'int')->autoIncrement();
                $table->addColumn('ipa', 'int');
                $table->addColumn('uid', 'int');
                $table->addColumn('mtime', 'int');
                $table->addPrimaryKey('id');
            });
        }

        if(!$sm->tableExists('xftt_snatched')) {
            $sm->createTable('xftt_snatched', function (Create $table) {
                $table->addColumn('user_id', 'int')->setDefault(0);
                $table->addColumn('mtime', 'int')->setDefault(0);
                $table->addColumn('torrent_id', 'int')->setDefault(0);
                $table->addColumn('ipa', 'int')->setDefault(0);
                $table->addKey('torrent_id', 'torrent_id');
                $table->addKey('user_id', 'user_id');
            });
        }

        if(!$sm->tableExists('xftt_request_reseed')) {
            $sm->createTable('xftt_request_reseed', function (Create $table) {
                $table->addColumn('user_id', 'int')->setDefault(0);
                $table->addColumn('torrent_id', 'int')->setDefault(0);
                $table->addKey('user_id', 'user_id');
            });
        }

        // Create Bonus Points Table
        if(!$sm->tableExists('xftt_bonus_points')) {
            $sm->createTable('xftt_bonus_points', function (Create $table) {
                $table->addColumn('id', 'int')->autoIncrement();
                $table->addColumn('data_receivable', 'int');
                $table->addColumn('points_needed', 'bigint', 20);
                $table->addColumn('display_order', 'int');
                $table->addPrimaryKey('id');
                $table->addUniqueKey('data_receivable');
                $table->addUniqueKey(['data_receivable', 'points_needed'], 'bonus_points');
            });
        }
    }

    // Alter Tables to add columns in xenforo tables at Installation
    public function installStep2()
    {

        $sm = $this->schemaManager();

    	if(!$sm->columnExists('xf_forum','torrent_category_image')) {
            $sm->alterTable('xf_forum', function (Alter $table) {
                $table->addColumn('torrent_category_image', 'varchar', 100)->setDefault('');
            });
        }

        if(!$sm->columnExists('xf_node','is_torrent_category')) {
            $sm->alterTable('xf_node', function (Alter $table) {
                $table->addColumn('is_torrent_category', 'tinyint')->setDefault(0);
                $table->addColumn('upload_multiplier','int')->setDefault(1);
                $table->addColumn('download_multiplier','int')->setDefault(1);
                $table->addKey('is_torrent_category', 'is_torrent_category');
            });
        }

        if(!$sm->columnExists('xf_thread','torrent_id')) {
            $sm->alterTable('xf_thread', function (Alter $table) {
                $table->addColumn('torrent_id', 'int')->setDefault(0);
            });
        }

        if(!$sm->columnExists('xf_user','torrent_pass_version')) {
            $sm->alterTable('xf_user', function (Alter $table) {
                $table->addColumn('torrent_pass_version', 'int')->setDefault(0);
                $table->addColumn('downloaded', 'bigint')->setDefault(0);
                $table->addColumn('uploaded', 'bigint')->setDefault(0);
                $table->addColumn('can_leech', 'tinyint')->setDefault(1);
                $table->addColumn('wait_time', 'int')->setDefault(0);
                $table->addColumn('peers_limit', 'int')->setDefault(0);
                $table->addColumn('torrent_pass', 'char', 32)->setDefault('');
                $table->addColumn('seedbonus', 'bigint')->setDefault(0);
                $table->addColumn('freeleech', 'tinyint')->setDefault(0);
                $table->addColumn('upload_multiplier','int')->setDefault(1);
                $table->addColumn('download_multiplier','int')->setDefault(1);
                $table->addColumn('torrent_upload_count','int')->setDefault(0);
            });
        }

        if(!$sm->columnExists('xf_user_upgrade','xentt_options')) {
            $sm->alterTable('xf_user_upgrade', function (Alter $table) {
                $table->addColumn('xentt_options', 'text');
            });
        }

    }

    //Insert Data into xftt_config and xftt_bonus_points
    public function installStep3()
    {
		$db = \XF::db();
		$options = \XF::options();

		  $db->query("
			INSERT INTO `xftt_config` 
				(`name`, `value`) 
			VALUES
				('announce_interval', '1800'),
				('anonymous_announce', '0'),
				('anonymous_scrape', '0'),
				('auto_register', '0'),
				('cheat_system', '0'),
				('clean_up_interval', '60'),
				('column_files_fid', 'torrent_id'),
				('column_users_uid', 'user_id'),
				('daemon', '1'),
				('debug', '0'),
				('freeleech', '0'),
				('full_scrape', '0'),
				('gzip_scrape', '1'),
				('listen_port', '2710'),
				('log_access', '0'),
				('log_announce', '0'),
				('log_scrape', '0'),
				('offline_message', ''),
				('pid_file', ''),
				('query_log', ''),
				('read_config_interval', '60'),
				('read_db_interval', '60'),
				('read_db_files_interval', '30'),
				('read_db_users_interval', '30'),
				('redirect_url', " . $db->quote($options->boardUrl) . "),
				('scrape_interval', '0'),
				('seedbonus', '1'),
				('seedbonus_interval', '3600'),
				('table_files', 'xftt_torrent'),
				('table_files_users', 'xftt_peer'),
				('table_users', 'xf_user'),
				('torrent_pass_private_key', " . $db->quote(self::makeSecret(27)) . "),
				('write_db_interval', '15'),
				('cloudfare', 0),
                ('global_multiplier',0),
                ('upload_multiplier',1),
                ('download_multiplier',1)
			");

        // Add Default Bonus Points to Database
        $db->query("
            INSERT INTO `xftt_bonus_points` 
                (`id`, `data_receivable`, `points_needed`,`display_order`) 
            VALUES
                (1,1,120,1),
                (2,3,330,2),
                (3,10,1000,3),
                (4,55,5000,4),
                (5,120,10000,5)
            ");
    }

    /**
     * Add .torrent extension in attachments option
     */
    public function installStep4()
    {
    	$options = \XF::options();

    	$db = \XF::db();

    	$attachmentExtensions = explode("\n", $options->attachmentExtensions);

    	if(!in_array('torrent', $attachmentExtensions))
    	{
    		$attachmentExtensions[] = 'torrent';

    		$db->update('xf_option', array(
				'option_value' => implode("\n", $attachmentExtensions)
			), 'option_id = \'attachmentExtensions\'');
    	}
    }


    /**
     * Creates Widget
     */
    public function installStep5()
    {
        $this->createWidget('torrent_tracker_mystats','mytracker_stats',[
            'positions' => ['forum_list_sidebar' => 10]],'My BitTorrent Stats');

        $this->createWidget('torrent_tracker_stats','torrenttracker_stats',[
            'positions' => ['forum_list_sidebar' => 10]],'BitTorrent Tracker Stats');
    }

    /**
     * @param array $stateChanges
     *
     * @throws \Exception
     * Applies Default Permissions once the addon is installed.
     */

    public function postInstall(array &$stateChanges)
    {
        if ($this->applyDefaultPermissions())
        {
            // since we're running this after data imports, we need to trigger a permission rebuild
            // if we changed anything
            $this->app->jobManager()->enqueueUnique(
                'permissionRebuild',
                'XF:PermissionRebuild',
                [],
                false
            );
        }
    }


    // ################################ UNINSTALL ####################

	//Uninstall addon and remove all data created by it in db
	public function uninstallStep1()
	{
        $sm = $this->schemaManager();

	    $sm->alterTable('xf_forum', function(Alter $table)
	    {
	        $table->dropColumns('torrent_category_image');
	    });

	    $sm->alterTable('xf_node', function(Alter $table)
	    {
	        $table->dropColumns('is_torrent_category');
            $table->dropColumns('upload_multiplier');
            $table->dropColumns('download_multiplier');
	    });

	    $sm->alterTable('xf_thread', function(Alter $table)
	    {
	        $table->dropColumns('torrent_id');
	    });

	    $sm->alterTable('xf_user', function(Alter $table)
	    {
	        $table->dropColumns('torrent_pass_version');
			$table->dropColumns('downloaded');
			$table->dropColumns('uploaded');
			$table->dropColumns('can_leech');
			$table->dropColumns('wait_time');
			$table->dropColumns('peers_limit');
			$table->dropColumns('torrent_pass');
			$table->dropColumns('seedbonus');
			$table->dropColumns('freeleech');
            $table->dropColumns('upload_multiplier');
            $table->dropColumns('download_multiplier');
	    });

	    $sm->alterTable('xf_user_upgrade', function(Alter $table)
	    {
	        $table->dropColumns('xentt_options');
	    });	    
	}

	public function uninstallStep2()
	{

        $sm = $this->schemaManager();

	    $sm->dropTable('xftt_announce_log');
	    $sm->dropTable('xftt_cheat_log');
	    $sm->dropTable('xftt_config');
	    $sm->dropTable('xftt_deny_from_clients');
	    $sm->dropTable('xftt_deny_from_hosts');
	    $sm->dropTable('xftt_freeleech_request');
	    $sm->dropTable('xftt_log');
	    $sm->dropTable('xftt_peer');
	    $sm->dropTable('xftt_request_reseed');
	    $sm->dropTable('xftt_scrap_log');
	    $sm->dropTable('xftt_snatched');
	    $sm->dropTable('xftt_torrent');
	    $sm->dropTable('xftt_torrent_info');
	    $sm->dropTable('xftt_bonus_points');
	}


    /**
     * Delete the Widget Created By Addon
     */
    public function uninstallStep3()
    {
        $this->deleteWidget('torrent_tracker_mystats');
        $this->deleteWidget('torrent_tracker_stats');
    }


    // ################################ Upgrades ####################
    public function upgrade2020370Step1()
    {
        $db = \XF::db();
        $db->query("UPDATE `xf_user` SET `seedbonus`=seedbonus/10");
    }

    public function upgrade2020538Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xftt_peer', function(Alter $table)
        {
            $table->addColumn('id', 'int')->autoIncrement();
        });
    }


    public function upgrade2020772Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('upload_multiplier','int')->setDefault(1);
            $table->addColumn('download_multiplier','int')->setDefault(1);
        });

        $sm->alterTable('xf_node',function(Alter $table)
        {
            $table->addColumn('upload_multiplier','int')->setDefault(1);
            $table->addColumn('download_multiplier','int')->setDefault(1);
        });

        $sm->alterTable('xftt_torrent',function(Alter $table)
        {
            $table->addColumn('upload_multiplier','int')->setDefault(1);
            $table->addColumn('download_multiplier','int')->setDefault(1);
        });
    }

    public function upgrade2020870Step1()
    {
        $db = \XF::db();

        $db->query("
            INSERT INTO `xftt_config` 
                (`name`, `value`) 
            VALUES
                ('upload_multiplier',1),
                ('download_multiplier',1)
            ");

    }

    public function upgrade2020972Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xftt_freeleech_request', function(Alter $table)
        {
            $table->addColumn('user_id', 'int')->setDefault(0);
        });
    }


    public function upgrade2020976Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xftt_torrent',function(Alter $table)
        {
            $table->addColumn('sticky', 'tinyint')->setDefault(0);
        });

    }


    //Drop Column torrent_id from XF Thread
    public function upgrade2021075Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_thread',function(Alter $table)
        {
            $table->dropColumns('torrent_id');
        });
    }

    //    Create Bonus Points DB Tables

    public function upgrade2021078Step1()
    {
        $sm = $this->schemaManager();

        if(!$sm->tableExists('xftt_bonus_points')) {
            $sm->createTable('xftt_bonus_points', function (Create $table) {
                $table->addColumn('id', 'int')->autoIncrement();
                $table->addColumn('data_receivable', 'int');
                $table->addColumn('points_needed', 'bigint', 20);
                $table->addColumn('display_order', 'int');
                $table->addPrimaryKey('id');
                $table->addUniqueKey('data_receivable');
                $table->addUniqueKey(['data_receivable', 'points_needed'], 'bonus_points');
            });
        }

        $db = \XF::db();

        $db->query("
            INSERT INTO `xftt_bonus_points` 
                (`id`, `data_receivable`, `points_needed`,`display_order`) 
            VALUES
                (1,1,120,1),
                (2,3,330,2),
                (3,10,1000,3),
                (4,55,5000,4),
                (5,120,10000,5)
            ");
    }

//    Convert SERIALIZED TO JSON STEPS for File Info of Torrents
    public function upgrade2021272Step1()
    {
        $this->entityColumnsToJson('TorrentTracker:Info', ['file_details'], 0, [], true);
    }

    /**
     * Add Upload Counter to User Table
     */
    public function upgrade2021375Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('torrent_upload_count','int')->setDefault(0);
        });

        $db = \XF::db();
        try {
            $db->query('UPDATE xf_user u SET u.torrent_upload_count = (select count(*) from xftt_torrent xt where xt.user_id = u.user_id)');
        } catch (Exception $e) {
            \XF::logError($e->getMessage());
        }
    }


    // ################################ Permissions ####################

	/***
    * Apply Global Permissions
    ***/

	 protected function applyDefaultPermissions($previousVersion = null)
     {
         $applied = false;

         if (!$previousVersion)
         {
             $this->applyGlobalPermission('xenTorrentTracker', 'view', 'forum', 'viewOthers');
             $this->applyGlobalPermission('xenTorrentTracker', 'download', 'forum', 'viewAttachment');
             $this->applyGlobalPermission('xenTorrentTracker', 'upload', 'forum', 'uploadAttachment');
             $this->applyGlobalPermission('xenTorrentTracker', 'viewSnatchList', 'forum', 'viewContent');
             $this->applyGlobalPermission('xenTorrentTracker', 'viewPeerList', 'forum', 'viewContent');
             $this->applyGlobalPermission('xenTorrentTracker', 'canMakeFreeleech', 'forum', 'hardDeleteAnyPost');
             $this->applyGlobalPermission('xenTorrentTracker', 'sentfreeleechrequest', 'forum', 'viewAttachment');
             $this->applyGlobalPermission('xenTorrentTracker', 'viewMemberTorrentTabs', 'general', 'viewProfile');
             $this->applyGlobalPermission('xenTorrentTracker','stickUnstickTorrent','forum','stickUnstickThread');
             $this->applyGlobalPermission('xenTorrentTracker','canDownloadOwnTorrents','forum','viewAttachment');


             $applied = true;
         }

         return $applied;
     }

    // ################################ Generates Secret Key ####################

    protected function makeSecret($length = 32) 
    {
        $secret = '';
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $charLen = strlen($chars) - 1;
        for ($i = 0; $i < $length; ++$i) 
        {
            $secret .= $chars[mt_rand(0, $charLen)];
        }
        
        return $secret;
    }
}