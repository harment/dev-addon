<?php
// src/addons/Harment/XBTTracker/Cron/SyncUsers.php
namespace Harment\XBTTracker\Cron;

/**
 * Cron entry for synchronizing users between XenForo and XBT Tracker
 * يقوم بمزامنة بيانات المستخدمين بين XenForo والتراكر
 */
class SyncUsers
{
    /**
     * Synchronize users between XenForo and XBT Tracker
     * This method is called automatically by XenForo's cron system
     *
     * @param int $d Days since last run (unused, kept for compatibility)
     * @param int $h Hours since last run (unused, kept for compatibility)
     * @param int $m Minutes since last run (unused, kept for compatibility)
     * @return bool
     */
    public static function syncUsers($d = 0, $h = 0, $m = 0)
    {
        \XF::logDebug('XBT Tracker: Starting user synchronization');
        
        $startTime = microtime(true);
        $db = \XF::db();
        $syncCount = 0;
        $createdCount = 0;
        
        try {
            // 1. تحديد المستخدمين النشطين في XenForo الذين ليس لديهم سجلات في التراكر
            $usersWithoutTrackerOptions = $db->fetchAllColumn("
                SELECT u.user_id 
                FROM xf_user AS u
                LEFT JOIN xf_harment_xbttracker_user_options AS to1 ON u.user_id = to1.user_id
                WHERE u.user_state = 'valid' 
                AND u.is_banned = 0
                AND to1.user_id IS NULL
                LIMIT 100
            ");
            
            if (!empty($usersWithoutTrackerOptions)) {
                // إنشاء خيارات المستخدم للمستخدمين الذين ليس لديهم
                $createdCount = count($usersWithoutTrackerOptions);
                
                $em = \XF::em();
                $db->beginTransaction();
                
                foreach ($usersWithoutTrackerOptions as $userId) {
                    $user = $em->find('XF:User', $userId);
                    if (!$user) {
                        continue;
                    }
                    
                    // إنشاء خيارات التراكر للمستخدم
                    $options = $em->create('Harment\XBTTracker:UserOptions');
                    $options->user_id = $userId;
                    
                    // إنشاء مفتاح الوصول (passkey)
                    $data = $userId . $user->username . \XF::$time . \XF::generateRandomString(16);
                    $options->passkey = md5($data);
                    $options->can_download = true;
                    $options->can_upload = true;
                    $options->save();
                    
                    // إنشاء إحصائيات التراكر للمستخدم
                    $stats = $em->create('Harment\XBTTracker:UserStats');
                    $stats->user_id = $userId;
                    $stats->save();
                }
                
                $db->commit();
                \XF::logDebug("XBT Tracker: Created tracker options for {$createdCount} users");
            }
            
            // 2. مزامنة البيانات من وإلى التراكر الخارجي (إذا كان مستخدمًا)
            if (\XF::app()->offsetExists('harment.xbttracker.service')) {
                /** @var \Harment\XBTTracker\Service\Tracker $trackerService */
                $trackerService = \XF::app()->get('harment.xbttracker.service');
                
                if (method_exists($trackerService, 'syncUsersWithExternalTracker')) {
                    $syncCount = $trackerService->syncUsersWithExternalTracker();
                    \XF::logDebug("XBT Tracker: Synchronized {$syncCount} users with external tracker");
                }
            }
            
            // 3. مزامنة معلومات المجموعات
            $syncGroupCount = self::syncUserGroups();
            \XF::logDebug("XBT Tracker: Synchronized tracker permissions for {$syncGroupCount} user groups");
            
            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);
            \XF::logDebug("XBT Tracker: User synchronization completed in {$executionTime} seconds");
            
            return true;
        } catch (\Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            
            \XF::logException($e, false, 'XBT Tracker user sync error: ');
            return false;
        }
    }
    
    /**
     * Synchronize user groups with tracker permissions
     * 
     * @return int Number of groups synchronized
     */
    protected static function syncUserGroups()
    {
        $db = \XF::db();
        $syncCount = 0;
        
        try {
            // خذ معلومات المجموعات وصلاحياتها
            $userGroups = \XF::finder('XF:UserGroup')->fetch();
            
            if ($userGroups->count()) {
                $db->beginTransaction();
                
                foreach ($userGroups as $group) {
                    // الحصول على صلاحيات المجموعة المتعلقة بالتراكر
                    $permissions = $db->fetchRow("
                        SELECT 
                            MAX(CASE WHEN permission_id = 'view' THEN permission_value ELSE 0 END) AS can_view,
                            MAX(CASE WHEN permission_id = 'download' THEN permission_value ELSE 0 END) AS can_download,
                            MAX(CASE WHEN permission_id = 'upload' THEN permission_value ELSE 0 END) AS can_upload
                        FROM xf_permission_entry
                        WHERE user_group_id = ? AND permission_group_id = 'xbtTracker'
                    ", [$group->user_group_id]);
                    
                    if ($permissions) {
                        // تحديث خيارات التراكر للمستخدمين في هذه المجموعة
                        if (!empty($permissions['can_download']) || !empty($permissions['can_upload'])) {
                            $db->query("
                                UPDATE xf_harment_xbttracker_user_options AS options
                                INNER JOIN xf_user_group_relation AS relation ON options.user_id = relation.user_id
                                SET 
                                    options.can_download = ?,
                                    options.can_upload = ?
                                WHERE relation.user_group_id = ?
                            ", [
                                !empty($permissions['can_download']) ? 1 : 0,
                                !empty($permissions['can_upload']) ? 1 : 0,
                                $group->user_group_id
                            ]);
                            
                            $syncCount++;
                        }
                    }
                }
                
                $db->commit();
            }
            
            return $syncCount;
        } catch (\Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            
            \XF::logException($e, false, 'XBT Tracker user group sync error: ');
            return 0;
        }
    }
    
    /**
     * Force regenerate all passkeys - called manually when needed
     * 
     * @return int Number of passkeys regenerated
     */
    public static function regenerateAllPasskeys()
    {
        $db = \XF::db();
        $count = 0;
        
        try {
            $users = \XF::finder('XF:User')
                ->where('user_state', 'valid')
                ->where('is_banned', 0)
                ->fetch();
            
            if ($users->count()) {
                $db->beginTransaction();
                
                foreach ($users as $user) {
                    $userId = $user->user_id;
                    $options = \XF::em()->find('Harment\XBTTracker:UserOptions', $userId);
                    
                    if (!$options) {
                        $options = \XF::em()->create('Harment\XBTTracker:UserOptions');
                        $options->user_id = $userId;
                    }
                    
                    // إنشاء مفتاح الوصول (passkey) جديد
                    $data = $userId . $user->username . \XF::$time . \XF::generateRandomString(16);
                    $options->passkey = md5($data);
                    $options->save();
                    
                    $count++;
                }
                
                $db->commit();
                \XF::logDebug("XBT Tracker: Regenerated {$count} passkeys");
            }
            
            return $count;
        } catch (\Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            
            \XF::logException($e, false, 'XBT Tracker passkey regeneration error: ');
            return 0;
        }
    }
}