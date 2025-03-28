<?php

namespace TorrentManager;

use XF\AddOn\AbstractSetup;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    public function install(array $stepParams = [])
    {
        $sm = $this->schemaManager();

        // إنشاء جدول التورنت
        if ($sm->tableExists('xf_torrent')) {
            $sm->dropTable('xf_torrent');
        }
        $sm->createTable('xf_torrent', function(Create $table) 
        {
            $table->addColumn('torrent_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('title', 'varchar', 255);
            $table->addColumn('category_id', 'int');
            $table->addColumn('file_path', 'varchar', 255);
            $table->addColumn('hash', 'varchar', 40);
            $table->addColumn('video_type', 'varchar', 50);
            $table->addColumn('audio_type', 'varchar', 50);
            $table->addColumn('audio_channels', 'varchar', 10);
            $table->addColumn('poster_url', 'varchar', 255);
            $table->addColumn('tmdb_link', 'varchar', 255)->nullable();
            $table->addColumn('upload_date', 'int');
            $table->addColumn('overview', 'text');
            $table->addColumn('status', 'enum')->values(['active', 'dead'])->setDefault('active');
            $table->addPrimaryKey('torrent_id');
            $table->addKey('user_id');
        });

{
    $this->db()->insert('xf_route', [
        'route_prefix' => 'torrent-manager',
        'route_type' => 'admin',
        'sub_name' => 'settings',
        'addon_id' => 'TorrentManager',
        'controller' => 'TorrentManager\\Admin\\Controller\\Settings'
    ]);
}
        // إنشاء جدول بيانات المستخدمين مع التورنت
        $sm->createTable('xf_torrent_user', function(Create $table)
        {
            $table->addColumn('user_id', 'int');
            $table->addColumn('torrent_id', 'int');
            $table->addColumn('uploaded', 'bigint')->setDefault(0);
            $table->addColumn('downloaded', 'bigint')->setDefault(0);
            $table->addColumn('ratio', 'float')->setDefault(0);
            $table->addColumn('seed_time', 'int')->setDefault(0);
            $table->addColumn('bonus_points', 'int')->setDefault(0);
            $table->addColumn('warnings', 'int')->setDefault(0);
            $table->addPrimaryKey(['user_id', 'torrent_id']);
        });

        // إضافة خيار TMDB API Key
        $option = $this->app->em()->create('XF:Option');
        $option->option_id = 'tmdb_api_key';
        $option->option_value = '';
        $option->edit_format = 'textbox';
        $option->data_type = 'string';
        $option->addon_id = 'TorrentManager';
        $option->save();

        // تحديد معرف مجموعة واجهة الأذونات
        $interfaceGroupId = 'torrentManagerPermissions'; // معرف فريد لمجموعة الأذونات

        // التحقق من وجود مجموعة واجهة الأذونات
        $existingInterfaceGroup = \XF::em()->findOne('XF:PermissionInterfaceGroup', ['interface_group_id' => $interfaceGroupId]);

        if (!$existingInterfaceGroup) {
            // إنشاء المجموعة إذا لم تكن موجودة
            $interfaceGroup = \XF::em()->create('XF:PermissionInterfaceGroup');
            $interfaceGroup->interface_group_id = $interfaceGroupId;
            $interfaceGroup->display_order = 1000;
            $interfaceGroup->is_moderator = false;
            $interfaceGroup->save();
        }

        // تحديد معرف الإذن الفردي ومجموعته
        $permissionId = 'canUploadTorrent'; // معرف الإذن الفردي
        $permissionGroupId = 'forum'; // يمكن تغييره حسب الحاجة

        // التحقق من وجود الإذن الفردي
        $existingPermission = \XF::em()->findOne('XF:Permission', [
            'permission_group_id' => $permissionGroupId,
            'permission_id' => $permissionId
        ]);

        if (!$existingPermission) {
            // إنشاء الإذن إذا لم يكن موجودًا
            $permission = \XF::em()->create('XF:Permission');
            $permission->permission_group_id = $permissionGroupId;
            $permission->permission_id = $permissionId;
            $permission->interface_group_id = $interfaceGroupId;
            $permission->depend_permission_id = '';
            $permission->permission_type = 'flag';
            $permission->display_order = 10;
            $permission->addon_id = 'TorrentManager';
            $permission->save();
        }

        // إضافة التوجيه لصفحة التورنت
        $this->app->router()->addRoute('torrents', 'torrents/', 'TorrentManager:Index');
    }

    public function uninstall(array $stepParams = [])
    {
        $sm = $this->schemaManager();
        $sm->dropTable('xf_torrent');
        $sm->dropTable('xf_torrent_user');
    }




    public function upgrade(array $stepParams = [])
    {
        // كود الترقية، يمكن تركه فارغًا الآن
    }

    public function postInstall(array &$stateChanges)
    {
        $cronId = 'torrentStats'; // المعرف الفريد لوظيفة الجدولة

        // التحقق مما إذا كانت وظيفة الجدولة موجودة بالفعل
        $existingCron = \XF::em()->findOne('XF:CronEntry', ['entry_id' => $cronId]);

        if (!$existingCron) {
            // إذا لم تكن موجودة، قم بإنشائها
            $cron = \XF::em()->create('XF:CronEntry');
            $cron->entry_id = $cronId;
            $cron->cron_class = 'TorrentManager\Job\TorrentStats';
            $cron->cron_method = 'updateStats';
            $cron->active = true;
            $cron->next_run = \XF::$time + 3600; // تشغيل بعد ساعة
            $cron->run_rules = ['hours' => '*', 'minutes' => '0']; // تشغيل كل ساعة
            $cron->save();
        }
    }
}