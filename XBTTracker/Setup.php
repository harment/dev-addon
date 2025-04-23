<?php

namespace Harment\XBTTracker;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\Db\Schema\Create;
use XF\Db\Schema\Alter;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait, StepRunnerUpgradeTrait, StepRunnerUninstallTrait;

    // ---- التثبيت ----
    // لا نقوم بتعديل الدالة install الأساسية، السمة StepRunnerInstallTrait ستتولى ذلك

    public function installStep1()
    {
        $this->createDirectories();
        return true; // يجب إرجاع true للإشارة إلى نجاح الخطوة
    }

    public function installStep2()
    {
        $this->createStructure();
        return true;
    }

    public function installStep3()
    {
        $this->createInitialCategories();
        return true;
    }

    public function installStep4()
    {
        // الإذونات والخيارات تتم إدارتها عبر ملفات XML
        $this->registerUserExtension();
        return true;
    }

    // ---- الترقية ----
    // لا نقوم بتعديل الدالة upgrade الأساسية

    public function upgradeStep1($previousVersion, array $stepsRun)
    {
        // إعادة تطبيق امتدادات الفئات
        $this->registerUserExtension();

        // مثال: الترقية من إصدار قديم
        if ($previousVersion < 1000070) {
            $this->alterStructure();
        }
        
        return true;
    }

    // ---- إلغاء التثبيت ----
    // لا نقوم بتعديل الدالة uninstall الأساسية

    public function uninstallStep1()
    {
        $this->deleteStructure();
        return true;
    }

    public function uninstallStep2()
    {
        $this->deleteDirectories();
        return true;
    }

    public function uninstallStep3()
    {
        // حذف امتدادات الفئة من قاعدة البيانات
        try {
            $db = $this->db();
            $db->delete('xf_class_extension', 'addon_id = ?', 'Harment/XBTTracker');
        } catch (\Exception $e) {
            \XF::logError("Error removing class extensions: " . $e->getMessage());
        }
        return true;
    }

    /**
     * تسجيل امتداد المستخدم في قاعدة البيانات مباشرة
     */
    protected function registerUserExtension()
    {
        try {
            // التحقق من وجود كلاس امتداد المستخدم Entity
            $extensionFile = \XF::getAddOnDirectory() . '/Harment/XBTTracker/XF/Entity/User.php';
            if (!file_exists($extensionFile)) {
                // إنشاء ملف الامتداد إذا لم يكن موجوداً
                $this->createUserExtensionFile($extensionFile);
            }
            
            // التحقق من وجود كلاس امتداد المستخدم Repository
            $repoFile = \XF::getAddOnDirectory() . '/Harment/XBTTracker/XF/Repository/User.php';
            if (!file_exists($repoFile)) {
                // إنشاء ملف الامتداد إذا لم يكن موجوداً
                $this->createUserRepositoryFile($repoFile);
            }
            
            // التسجيل المباشر في قاعدة البيانات
            $db = $this->db();
            
            // تسجيل Entity User
            $db->query("
                INSERT INTO xf_class_extension 
                    (from_class, to_class, addon_id)
                VALUES 
                    ('XF\\\\Entity\\\\User', 'Harment\\\\XBTTracker\\\\XF\\\\Entity\\\\User', 'Harment/XBTTracker')
                ON DUPLICATE KEY UPDATE 
                    to_class = VALUES(to_class)
            ");
            
            // تسجيل Repository User
            $db->query("
                INSERT INTO xf_class_extension 
                    (from_class, to_class, addon_id)
                VALUES 
                    ('XF\\\\Repository\\\\User', 'Harment\\\\XBTTracker\\\\XF\\\\Repository\\\\User', 'Harment/XBTTracker')
                ON DUPLICATE KEY UPDATE 
                    to_class = VALUES(to_class)
            ");
            
            \XF::logError("Registered User extension classes successfully");
        } catch (\Exception $e) {
            \XF::logError("Error registering User extensions: " . $e->getMessage());
        }
    }

    /**
     * إنشاء ملف امتداد المستخدم في Entity
     *
     * @param string $path مسار الملف
     */
    protected function createUserExtensionFile($path)
    {
        $directory = dirname($path);
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0755, true)) {
                \XF::logError("Failed to create directory: $directory");
                return;
            }
        }

        $content = <<<'PHP'
<?php

namespace Harment\XBTTracker\XF\Entity;

class User extends XFCP_User
{
    /**
     * Get the user's tracker statistics
     * الحصول على إحصائيات المتتبع للمستخدم
     *
     * @return \Harment\XBTTracker\Entity\UserStats|null
     */
    public function getTrackerStats()
    {
        return $this->em()->find('XBTTracker:UserStats', $this->user_id);
    }
    
    /**
     * Check if user has tracker stats
     * التحقق مما إذا كان المستخدم لديه إحصائيات متتبع
     *
     * @return bool
     */
    public function hasTrackerStats()
    {
        return (bool)$this->getTrackerStats();
    }
    
    /**
     * Create tracker stats for this user if they don't exist
     * إنشاء إحصائيات المتتبع لهذا المستخدم إذا لم تكن موجودة
     *
     * @return \Harment\XBTTracker\Entity\UserStats
     */
    public function getOrCreateTrackerStats()
    {
        $stats = $this->getTrackerStats();
        
        if (!$stats) {
            $stats = \Harment\XBTTracker\Entity\UserStats::createForUser($this->user_id);
        }
        
        return $stats;
    }
}
PHP;

        if (!file_put_contents($path, $content)) {
            \XF::logError("Failed to write User extension file at: $path");
            return;
        }
        
        // تأكيد أن الملف تم إنشاؤه
        if (!file_exists($path)) {
            \XF::logError("Failed to create User extension file at: $path");
        } else {
            \XF::logError("Created User extension file at: $path");
        }
    }

    /**
     * إنشاء ملف امتداد Repository للمستخدم
     * 
     * @param string $path مسار الملف
     */
    protected function createUserRepositoryFile($path)
    {
        $directory = dirname($path);
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0755, true)) {
                \XF::logError("Failed to create directory: $directory");
                return;
            }
        }

        $content = <<<'PHP'
<?php

namespace Harment\XBTTracker\XF\Repository;

class User extends XFCP_User
{
    /**
     * Get users with torrent statistics
     *
     * @param array $conditions
     * @param array $fetchOptions
     * @return \XF\Mvc\Entity\Finder
     */
    public function findTorrentUsers(array $conditions = [], array $fetchOptions = [])
    {
        $finder = $this->finder('XF:User');
        
        $finder->with('XBTTracker:UserStats');
        
        foreach ($conditions as $field => $value) {
            $finder->where($field, $value);
        }
        
        return $finder;
    }
    
    /**
     * Get top uploaders
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getTopUploaders($limit = 10)
    {
        $db = $this->db();
        
        $userIds = $db->fetchAllColumn("
            SELECT t.user_id
            FROM xf_xbt_torrents AS t
            GROUP BY t.user_id
            ORDER BY COUNT(*) DESC
            LIMIT ?
        ", $limit);
        
        if (!$userIds) {
            return $this->em->getEmptyCollection();
        }
        
        return $this->finder('XF:User')
            ->where('user_id', $userIds)
            ->fetch();
    }
    
    /**
     * Get top seeders
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getTopSeeders($limit = 10)
    {
        $db = $this->db();
        
        $userIds = $db->fetchAllColumn("
            SELECT user_id
            FROM xf_xbt_user_stats
            ORDER BY active_seeds DESC
            LIMIT ?
        ", $limit);
        
        if (!$userIds) {
            return $this->em->getEmptyCollection();
        }
        
        return $this->finder('XF:User')
            ->where('user_id', $userIds)
            ->fetch();
    }
}
PHP;

        if (!file_put_contents($path, $content)) {
            \XF::logError("Failed to write User Repository extension file at: $path");
            return;
        }
        
        // تأكيد أن الملف تم إنشاؤه
        if (!file_exists($path)) {
            \XF::logError("Failed to create User Repository extension file at: $path");
        } else {
            \XF::logError("Created User Repository extension file at: $path");
        }
    }

    /**
     * إنشاء المجلدات اللازمة
     */
    protected function createDirectories()
    {
        $torrentsPath = 'data/torrents';
        \XF\Util\File::createDirectory($torrentsPath);
        \XF\Util\File::createDirectory($torrentsPath . '/posters');

        // التأكد من وجود مجلد XF/Entity
        $entityPath = \XF::getAddOnDirectory() . '/Harment/XBTTracker/XF/Entity';
        \XF\Util\File::createDirectory($entityPath, false);
        
        // التأكد أيضًا من وجود مجلد XF/Repository
        $repoPath = \XF::getAddOnDirectory() . '/Harment/XBTTracker/XF/Repository';
        \XF\Util\File::createDirectory($repoPath, false);
    }

    /**
     * التحقق من وجود جدول
     * 
     * @param string $tableName
     * @return bool
     */
    protected function doesTableExist($tableName)
    {
        $db = $this->db();
        try {
            $exists = $db->fetchOne("SHOW TABLES LIKE ?", $tableName);
            return !empty($exists);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * إنشاء هيكل قاعدة البيانات
     */
    protected function createStructure()
    {
        $sm = $this->schemaManager();

        // جدول التورنت - تحقق من وجوده أولاً
        if (!$this->doesTableExist('xf_xbt_torrents')) {
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
        }

        // جدول الفئات - تحقق من وجوده أولاً
        if (!$this->doesTableExist('xf_xbt_categories')) {
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
        }

        // جدول إحصائيات المستخدم - تحقق من وجوده أولاً
        if (!$this->doesTableExist('xf_xbt_user_stats')) {
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
        }

        // جدول الأقران (Peers) - تحقق من وجوده أولاً
        if (!$this->doesTableExist('xf_xbt_peers')) {
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
        }

        // جدول بيانات TMDB - تحقق من وجوده أولاً
        if (!$this->doesTableExist('xf_xbt_tmdb_data')) {
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
        }

        // جدول سجل المكافآت - تحقق من وجوده أولاً
        if (!$this->doesTableExist('xf_xbt_user_bonus_history')) {
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
        }

        // جدول سجل التحميلات المكتملة - تحقق من وجوده أولاً
        if (!$this->doesTableExist('xf_xbt_user_completed')) {
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

        // إضافة عمود xbt_passkey لجدول المستخدم - تحقق من وجوده أولاً
        if (!$sm->columnExists('xf_user', 'xbt_passkey')) {
            $sm->alterTable('xf_user', function(Alter $table) {
                $table->addColumn('xbt_passkey', 'varchar', 40)->nullable()->setDefault(null);
                $table->addKey('xbt_passkey');
            });
        }
    }
    
    /**
     * إنشاء الفئات الأولية
     */
    protected function createInitialCategories()
    {
        try {
            // إضافة الفئات الافتراضية
            $db = $this->db();
            
            // تأكد من وجود الجدول أولاً
            if ($this->doesTableExist('xf_xbt_categories')) {
                // التحقق من عدم وجود فئات مسبقًا
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
            }
        } catch (\Exception $e) {
            \XF::logError("Error creating initial categories: " . $e->getMessage());
        }
    }
    
    /**
     * تعديل هيكل قاعدة البيانات في الترقيات
     */
    protected function alterStructure()
    {
        $sm = $this->schemaManager();
        
        // مثال: إضافة عمود جديد
        if ($sm->tableExists('xf_xbt_torrents') && !$sm->columnExists('xf_xbt_torrents', 'new_column')) {
            $sm->alterTable('xf_xbt_torrents', function(Alter $table) {
                $table->addColumn('new_column', 'varchar', 50)->setDefault('');
            });
        }
    }
    
    /**
     * حذف الجداول عند إلغاء التثبيت
     */
    protected function deleteStructure()
    {
        $db = $this->db();
        
        // قائمة الجداول التي نريد حذفها
        $tables = [
            'xf_xbt_torrents',
            'xf_xbt_categories',
            'xf_xbt_user_stats',
            'xf_xbt_peers',
            'xf_xbt_tmdb_data',
            'xf_xbt_user_bonus_history',
            'xf_xbt_user_completed'
        ];
        
        foreach ($tables as $table) {
            try {
                $db->query("DROP TABLE IF EXISTS `$table`");
            } catch (\Exception $e) {
                \XF::logError("Error dropping table $table: " . $e->getMessage());
            }
        }
        
        // حذف عمود xbt_passkey من جدول المستخدم
        $sm = $this->schemaManager();
        if ($sm->columnExists('xf_user', 'xbt_passkey')) {
            try {
                $sm->alterTable('xf_user', function(Alter $table) {
                    $table->dropColumns('xbt_passkey');
                });
            } catch (\Exception $e) {
                \XF::logError("Error dropping column xbt_passkey: " . $e->getMessage());
            }
        }
    }
    
    /**
     * حذف المجلدات والملفات عند إلغاء التثبيت
     */
    protected function deleteDirectories()
    {
        $torrentsPath = 'data/torrents';
        
        // إضافة ملف تنبيه بدلاً من حذف الملفات
        try {
            $warningFile = $torrentsPath . '/ADDON_UNINSTALLED.txt';
            file_put_contents($warningFile, "The XBT Tracker add-on has been uninstalled.\nThis directory contains torrent files that were not automatically deleted.\nYou may safely delete this directory if you no longer need these files.");
        } catch (\Exception $e) {
            // تجاهل أي خطأ
        }
    }
}