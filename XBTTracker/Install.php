<?php

namespace XBTTracker;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\Db\Schema\Create;
use XF\Db\Schema\Alter;

class Install extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    // هذه هي الدالة التي سيتم استدعاؤها من XenForo
    public function install(array $stepParams = [])
    {
        // تسجيل مباشر في ملف السجل للتأكد من استدعاء هذه الدالة
        \XF::logError("XBTTracker installation started");
        
        $this->installStep1();
        $this->installStep2();
        $this->installStep3();
        $this->installStep4();
        $this->installStep5();
    }

 
    protected function installStep1()
    {
        // تسجيل الخيارات أولاً
        
    }

    public function installStep2()
    {
        // إنشاء المجلدات اللازمة
        $this->createDirectories();
    }
    
    public function installStep3()
    {
        // إنشاء الجداول الرئيسية
        $this->createStructure();
    }

    public function installStep4()
    {
        // إنشاء محتوى البيانات الأولي
        $this->createInitialData();
    }

    public function installStep5()
    {
        // إنشاء الإذونات الافتراضية
        $this->setupDefaultPermissions();
    }

    /**
     * خطوات الترقية
     */
    public function upgradeStep1000070()
    {
        // مثال لخطوة ترقية من الإصدار 1.0.0 إلى الإصدار 1.0.1
        // تعديل هيكل الجداول عند الحاجة
        // $this->alterStructure();
    }

    /**
     * خطوات إلغاء التثبيت
     */
    public function uninstallStep1()
    {
        // حذف الجداول
        $this->deleteStructure();
    }

    public function uninstallStep2()
    {
        // حذف المجلدات والملفات
        $this->deleteDirectories();
    }

    public function uninstallStep3()
    {
        // إزالة البيانات المتعلقة بالإضافة من الجداول الأخرى
        $this->deleteRelatedData();
    }

    /**
     * إنشاء المجلدات اللازمة
     * ملاحظة: تم نقلها قبل createStructure للتأكد من وجود المجلدات قبل إنشاء هياكل البيانات
     */
    protected function createDirectories()
    {
        $options = \XF::app()->options();
        $torrentsPath = $options->xbtTrackerTorrentPath;
        
        if (!$torrentsPath || !strlen($torrentsPath)) {
            $torrentsPath = 'data/torrents';
        }

        \XF\Util\File::createDirectory($torrentsPath);
        \XF\Util\File::createDirectory($torrentsPath . '/posters');
    }

    /**
     * إنشاء هيكل قاعدة البيانات
     */
    protected function createStructure()
    {
        $sm = $this->schemaManager();

        // جدول التورنت
        $sm->createTable('xf_xbt_torrents', function(Create $table)
        {
            $table->addColumn('torrent_id', 'int')->autoIncrement();
            $table->addColumn('title', 'varchar', 255);
            $table->addColumn('description', 'text')->nullable();
            $table->addColumn('info_hash', 'varchar', 40);
            $table->addColumn('file_path', 'varchar', 255);
            $table->addColumn('poster_path', 'varchar', 255)->setDefault('');
            $table->addColumn('size', 'bigint')->unsigned();
            $table->addColumn('category_id', 'int')->unsigned();
            $table->addColumn('user_id', 'int')->unsigned();
            $table->addColumn('video_quality', 'varchar', 50)->setDefault('');
            $table->addColumn('audio_format', 'varchar', 50)->setDefault('');
            $table->addColumn('audio_channels', 'varchar', 10)->setDefault('');
            $table->addColumn('tmdb_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('seeders', 'int')->unsigned()->setDefault(0);
            $table->addColumn('leechers', 'int')->unsigned()->setDefault(0);
            $table->addColumn('completed', 'int')->unsigned()->setDefault(0);
            $table->addColumn('is_freeleech', 'tinyint', 1)->unsigned()->setDefault(0);
            $table->addColumn('creation_date', 'int')->unsigned();
            $table->addColumn('view_count', 'int')->unsigned()->setDefault(0);
            
            $table->addPrimaryKey('torrent_id');
            $table->addKey('info_hash');
            $table->addKey(['user_id', 'creation_date']);
            $table->addKey(['category_id', 'creation_date']);
        });

        // جدول الفئات
        $sm->createTable('xf_xbt_categories', function(Create $table)
        {
            $table->addColumn('category_id', 'int')->autoIncrement();
            $table->addColumn('title', 'varchar', 100);
            $table->addColumn('description', 'text')->nullable();
            $table->addColumn('parent_id', 'int')->unsigned()->setDefault(0);
            $table->addColumn('display_order', 'int')->unsigned()->setDefault(1);
            $table->addColumn('node_id', 'int')->unsigned()->setDefault(0);
            
            $table->addPrimaryKey('category_id');
            $table->addKey('parent_id');
            $table->addKey('node_id');
        });

        // جدول إحصائيات المستخدم
        $sm->createTable('xf_xbt_user_stats', function(Create $table)
        {
            $table->addColumn('user_id', 'int')->unsigned();
            $table->addColumn('passkey', 'varchar', 40)->setDefault('');
            $table->addColumn('uploaded', 'bigint')->unsigned()->setDefault(0);
            $table->addColumn('downloaded', 'bigint')->unsigned()->setDefault(0);
            $table->addColumn('bonus_points', 'int')->unsigned()->setDefault(0);
            $table->addColumn('warnings', 'int')->unsigned()->setDefault(0);
            $table->addColumn('active_seeds', 'int')->unsigned()->setDefault(0);
            $table->addColumn('active_leech', 'int')->unsigned()->setDefault(0);
            
            $table->addPrimaryKey('user_id');
        });

        // جدول الأقران (Peers)
        $sm->createTable('xf_xbt_peers', function(Create $table)
        {
            $table->addColumn('peer_id', 'int')->autoIncrement();
            $table->addColumn('torrent_id', 'int')->unsigned();
            $table->addColumn('user_id', 'int')->unsigned();
            $table->addColumn('peer_id_binary', 'binary', 20);
            $table->addColumn('ip', 'varchar', 45);
            $table->addColumn('port', 'int')->unsigned();
            $table->addColumn('uploaded', 'bigint')->unsigned()->setDefault(0);
            $table->addColumn('downloaded', 'bigint')->unsigned()->setDefault(0);
            $table->addColumn('left_bytes', 'bigint')->unsigned();
            $table->addColumn('seeder', 'tinyint', 1)->unsigned();
            $table->addColumn('first_announce', 'int')->unsigned();
            $table->addColumn('last_announce', 'int')->unsigned();
            $table->addColumn('completed', 'tinyint', 1)->unsigned()->setDefault(0);
            $table->addColumn('hit_and_run_warned', 'tinyint', 1)->unsigned()->setDefault(0);
            $table->addColumn('passkey', 'varchar', 40);
            
            $table->addPrimaryKey('peer_id');
            $table->addUniqueKey(['torrent_id', 'user_id']);
            $table->addKey(['torrent_id', 'seeder']);
            $table->addKey(['user_id', 'seeder']);
        });

        // جدول بيانات TMDB
        $sm->createTable('xf_xbt_tmdb_data', function(Create $table)
        {
            $table->addColumn('tmdb_id', 'int')->unsigned();
            $table->addColumn('type', 'varchar', 10)->setDefault('movie');
            $table->addColumn('title', 'varchar', 255);
            $table->addColumn('title_ar', 'varchar', 255)->setDefault('');
            $table->addColumn('overview', 'text')->nullable();
            $table->addColumn('overview_ar', 'text')->nullable();
            $table->addColumn('poster_path', 'varchar', 255)->setDefault('');
            $table->addColumn('backdrop_path', 'varchar', 255)->setDefault('');
            $table->addColumn('release_date', 'varchar', 20)->setDefault('');
            $table->addColumn('vote_average', 'float')->setDefault(0);
            $table->addColumn('cast', 'blob');
            $table->addColumn('crew', 'blob');
            $table->addColumn('fetch_date', 'int')->unsigned();
            
            $table->addPrimaryKey('tmdb_id');
        });

        // جدول سجل المكافآت
        $sm->createTable('xf_xbt_user_bonus_history', function(Create $table)
        {
            $table->addColumn('bonus_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int')->unsigned();
            $table->addColumn('points', 'int');
            $table->addColumn('reason', 'varchar', 255);
            $table->addColumn('date', 'int')->unsigned();
            
            $table->addPrimaryKey('bonus_id');
            $table->addKey(['user_id', 'date']);
        });

        // جدول سجل التحميلات المكتملة
        $sm->createTable('xf_xbt_user_completed', function(Create $table)
        {
            $table->addColumn('completed_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int')->unsigned();
            $table->addColumn('torrent_id', 'int')->unsigned();
            $table->addColumn('date', 'int')->unsigned();
            $table->addColumn('seeded_until', 'int')->unsigned()->setDefault(0);
            $table->addColumn('hit_and_run', 'tinyint', 1)->unsigned()->setDefault(0);
            
            $table->addPrimaryKey('completed_id');
            $table->addUniqueKey(['user_id', 'torrent_id']);
            $table->addKey('torrent_id');
        });
    }
    
    /**
     * إنشاء البيانات الأولية
     */
    protected function createInitialData()
    {
        // إضافة الفئات الافتراضية
        $db = $this->db();
        
        // التحقق من عدم وجود فئات مسبقًا (في حالة الترقية)
        $existingCategories = $db->fetchOne("SELECT COUNT(*) FROM xf_xbt_categories");
        
        if (!$existingCategories) {
            // إنشاء بعض الفئات الافتراضية
            $categories = [
                ['title' => 'Movies', 'description' => 'All movie torrents', 'parent_id' => 0, 'display_order' => 1],
                ['title' => 'TV Shows', 'description' => 'All TV show torrents', 'parent_id' => 0, 'display_order' => 2],
                ['title' => 'Music', 'description' => 'All music torrents', 'parent_id' => 0, 'display_order' => 3],
                ['title' => '1080p', 'description' => '1080p movies', 'parent_id' => 1, 'display_order' => 1],
                ['title' => '4K', 'description' => '4K movies', 'parent_id' => 1, 'display_order' => 2],
                ['title' => '720p', 'description' => '720p movies', 'parent_id' => 1, 'display_order' => 3],
                ['title' => 'BluRay', 'description' => 'BluRay movies', 'parent_id' => 1, 'display_order' => 4],
            ];
            
            foreach ($categories as $category) {
                $db->insert('xf_xbt_categories', $category);
            }
        }
        
        // إنشاء نموذج إعلام بالبريد الإلكتروني للتحذيرات والمكافآت
        $this->createMailTemplates();
    }
    
    /**
     * إنشاء قوالب البريد الإلكتروني
     */
    protected function createMailTemplates()
    {
        // تتم إضافة قوالب البريد الإلكتروني تلقائيًا من خلال ملفات القوالب
    }
    
    /**
     * إعداد الإذونات الافتراضية
     */
    protected function setupDefaultPermissions()
    {
        // تعيين الإذونات الافتراضية للمجموعات
        $db = $this->db();
        
        // إذونات للأعضاء المسجلين
        $this->applyGlobalPermissionForGroup(2, 'xbtTracker', 'view', 'allow');
        $this->applyGlobalPermissionForGroup(2, 'xbtTracker', 'download', 'allow');
        $this->applyGlobalPermissionForGroup(2, 'xbtTracker', 'upload', 'allow');
        $this->applyGlobalPermissionForGroup(2, 'xbtTracker', 'edit', 'allow');
        $this->applyGlobalPermissionForGroup(2, 'xbtTracker', 'delete', 'allow');
        
        // إذونات للمشرفين
        $this->applyGlobalPermissionForGroup(3, 'xbtTracker', 'view', 'allow');
        $this->applyGlobalPermissionForGroup(3, 'xbtTracker', 'download', 'allow');
        $this->applyGlobalPermissionForGroup(3, 'xbtTracker', 'upload', 'allow');
        $this->applyGlobalPermissionForGroup(3, 'xbtTracker', 'edit', 'allow');
        $this->applyGlobalPermissionForGroup(3, 'xbtTracker', 'delete', 'allow');
        $this->applyGlobalPermissionForGroup(3, 'xbtTracker', 'moderateTorrents', 'allow');
        
        // إذونات للزوار (لا شيء)
        $this->applyGlobalPermissionForGroup(1, 'xbtTracker', 'view', 'deny');
        $this->applyGlobalPermissionForGroup(1, 'xbtTracker', 'download', 'deny');
        $this->applyGlobalPermissionForGroup(1, 'xbtTracker', 'upload', 'deny');
    }
    
    /**
     * تعديل هيكل قاعدة البيانات في الترقيات
     */
    protected function alterStructure()
    {
        $sm = $this->schemaManager();
        
        // مثال: إضافة عمود جديد لجدول التورنت
        if ($sm->tableExists('xf_xbt_torrents') && !$sm->columnExists('xf_xbt_torrents', 'new_column')) {
            $sm->alterTable('xf_xbt_torrents', function(Alter $table) {
                $table->addColumn('new_column', 'varchar', 50)->setDefault('');
            });
        }
        
        // مثال: تعديل عمود موجود
        if ($sm->tableExists('xf_xbt_user_stats') && $sm->columnExists('xf_xbt_user_stats', 'column_to_alter')) {
            $sm->alterTable('xf_xbt_user_stats', function(Alter $table) {
                $table->changeColumn('column_to_alter')->type('bigint')->unsigned(true);
            });
        }
    }
    
    /**
     * حذف الجداول عند إلغاء التثبيت
     */
    protected function deleteStructure()
    {
        $sm = $this->schemaManager();
        
        $sm->dropTableIfExists('xf_xbt_torrents');
        $sm->dropTableIfExists('xf_xbt_categories');
        $sm->dropTableIfExists('xf_xbt_user_stats');
        $sm->dropTableIfExists('xf_xbt_peers');
        $sm->dropTableIfExists('xf_xbt_tmdb_data');
        $sm->dropTableIfExists('xf_xbt_user_bonus_history');
        $sm->dropTableIfExists('xf_xbt_user_completed');
    }
    
    /**
     * حذف المجلدات والملفات عند إلغاء التثبيت
     */
    protected function deleteDirectories()
    {
        $options = \XF::app()->options();
        $torrentsPath = $options->xbtTrackerTorrentPath;
        
        if (!$torrentsPath || !strlen($torrentsPath)) {
            $torrentsPath = 'data/torrents';
        }
        
        // لا نحذف ملفات التورنت تلقائيًا عند إلغاء التثبيت للحفاظ على البيانات
        // ولكن يمكن إضافة خيار لحذفها إذا طلب المستخدم ذلك
        // \XF\Util\File::deleteDirectory($torrentsPath);
    }
    
    /**
     * حذف البيانات المتعلقة بالإضافة من الجداول الأخرى
     */
    protected function deleteRelatedData()
    {
        $db = $this->db();
        
        // حذف الإذونات
        $db->delete('xf_permission_entry', "permission_group_id = 'xbtTracker'");
        
        // حذف الإعدادات
        $db->delete('xf_option', "option_id LIKE 'xbtTracker%'");
    }
    
    /**
     * تطبيق إذن عام لمجموعة مستخدمين
     *
     * @param int $userGroupId معرف مجموعة المستخدمين
     * @param string $permissionGroup مجموعة الإذن
     * @param string $permissionId معرف الإذن
     * @param string $value قيمة الإذن (allow/deny)
     */
    protected function applyGlobalPermissionForGroup($userGroupId, $permissionGroup, $permissionId, $value)
    {
        /** @var \XF\Entity\PermissionEntry $entry */
        $entry = \XF::em()->create('XF:PermissionEntry');
        $entry->user_group_id = $userGroupId;
        $entry->user_id = 0;
        $entry->permission_group_id = $permissionGroup;
        $entry->permission_id = $permissionId;
        $entry->permission_value = $value;
        $entry->save();
    }
}