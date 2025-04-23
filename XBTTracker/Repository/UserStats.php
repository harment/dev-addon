<?php
// src/addons/XBTTracker/Repository/UserStats.php
namespace Harment\XBTTracker\Repository;

use XF\Mvc\Entity\Repository;
use Harment\XBTTracker\Entity\UserStats as UserStatsEntity;
use Harment\XBTTracker\Entity\Torrent as TorrentEntity;

/**
 * User Statistics Repository
 * Provides methods for accessing and managing user statistics
 * 
 * مستودع إحصائيات المستخدم
 * يوفر طرق للوصول إلى وإدارة إحصائيات المستخدمين
 */
class UserStats extends Repository
{
    /**
     * Get user stats finder
     * الحصول على finder لإحصائيات المستخدم
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function getUserStatsFinder()
    {
        return $this->finder('Harment\XBTTracker:UserStats');
    }
    
    /**
     * Find user stats for list
     * العثور على إحصائيات المستخدم للقائمة
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findUserStatsForList()
    {
        return $this->getUserStatsFinder();
    }
    
    /**
     * Get user stats by user ID
     * الحصول على إحصائيات المستخدم حسب معرف المستخدم
     *
     * @param int $userId
     * @return UserStatsEntity|null
     */
    public function getUserStats($userId)
    {
        return $this->finder('Harment\XBTTracker:UserStats')
            ->where('user_id', $userId)
            ->fetchOne();
    }
    
    /**
     * Get or create user stats
     * الحصول على أو إنشاء إحصائيات المستخدم
     *
     * @param int $userId
     * @return UserStatsEntity
     */
    public function getOrCreateUserStats($userId)
    {
        $userStats = $this->getUserStats($userId);
        
        if (!$userStats) {
            /** @var UserStatsEntity $userStats */
            $userStats = $this->em()->create('Harment\XBTTracker:UserStats');
            $userStats->user_id = $userId;
            $userStats->passkey = $this->generatePasskey();
            $userStats->save();
        } else if (!$userStats->passkey) {
            // If passkey is empty, generate a new one
            // إذا كان مفتاح المرور فارغًا، قم بإنشاء واحد جديد
            $userStats->passkey = $this->generatePasskey();
            $userStats->save();
        }
        
        return $userStats;
    }
    
    /**
     * Generate a unique passkey
     * إنشاء مفتاح مرور فريد
     *
     * @return string
     */
    public function generatePasskey()
    {
        $passkey = bin2hex(random_bytes(20));
        
        // Check if passkey already exists
        // التحقق مما إذا كان مفتاح المرور موجود بالفعل
        $existing = $this->finder('Harment\XBTTracker:UserStats')
            ->where('passkey', $passkey)
            ->fetchOne();
        
        if ($existing) {
            // If exists, generate a new one
            // إذا كان موجودًا، قم بإنشاء واحد جديد
            return $this->generatePasskey();
        }
        
        return $passkey;
    }
    
    /**
     * Update user statistics
     * تحديث إحصائيات المستخدم
     *
     * @param int $userId
     * @return bool
     */
    public function updateUserStats($userId)
    {
        $db = $this->db();
        
        // Get active peer counts
        // الحصول على عدد الأقران النشطين
        $stats = $db->fetchRow("
            SELECT
                SUM(IF(seeder = 1, 1, 0)) AS active_seeds,
                SUM(IF(seeder = 0, 1, 0)) AS active_leech
            FROM xf_xbt_peers
            WHERE user_id = ?
        ", [$userId]);
        
        // Ensure user stats exist
        // التأكد من وجود إحصائيات للمستخدم
        $userStats = $this->getOrCreateUserStats($userId);
        
        if ($stats) {
            $userStats->active_seeds = $stats['active_seeds'] ?: 0;
            $userStats->active_leech = $stats['active_leech'] ?: 0;
            $userStats->save();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Update user statistics from tracker data
     * تحديث إحصائيات المستخدم من التراكر
     *
     * @param int $userId
     * @param int $uploaded
     * @param int $downloaded
     * @param int $activeSeeds
     * @param int $activeLeech
     * @return bool
     */
    public function updateUserStatsFromTracker($userId, $uploaded, $downloaded, $activeSeeds, $activeLeech)
    {
        $userStats = $this->getOrCreateUserStats($userId);
        
        $userStats->uploaded = $uploaded;
        $userStats->downloaded = $downloaded;
        $userStats->active_seeds = $activeSeeds;
        $userStats->active_leech = $activeLeech;
        
        return $userStats->save();
    }
    
    /**
     * Update all user statistics
     * تحديث إحصائيات جميع المستخدمين
     *
     * @return bool
     */
    public function updateAllUserStats()
    {
        $db = $this->db();
        
        // Use a single query to update all statistics
        // استخدام استعلام واحد لتحديث جميع الإحصائيات
        $db->query("
            UPDATE xf_xbt_user_stats AS us
            LEFT JOIN (
                SELECT 
                    user_id,
                    SUM(IF(seeder = 1, 1, 0)) AS active_seeds,
                    SUM(IF(seeder = 0, 1, 0)) AS active_leech
                FROM xf_xbt_peers
                GROUP BY user_id
            ) AS p ON (us.user_id = p.user_id)
            SET 
                us.active_seeds = IFNULL(p.active_seeds, 0),
                us.active_leech = IFNULL(p.active_leech, 0)
        ");
        
        return true;
    }
    
    /**
     * Award bonus points to a user
     * منح نقاط مكافأة للمستخدم
     *
     * @param int $userId
     * @param int $points
     * @param string $reason
     * @return UserStatsEntity|bool
     */
    public function awardBonusPoints($userId, $points, $reason)
    {
        if ($points <= 0) {
            return false;
        }
        
        $userStats = $this->getOrCreateUserStats($userId);
        
        $userStats->bonus_points += $points;
        $userStats->save();
        
        // Record bonus points history
        // تسجيل منح نقاط المكافأة
        $this->recordBonusHistory($userId, $points, $reason);
        
        return $userStats;
    }
    
    /**
     * Record bonus points history
     * تسجيل تاريخ نقاط المكافأة
     *
     * @param int $userId
     * @param int $points
     * @param string $reason
     * @return bool
     */
    public function recordBonusHistory($userId, $points, $reason)
    {
        /** @var \Harment\XBTTracker\Entity\BonusHistory $bonusHistory */
        $bonusHistory = $this->em()->create('Harment\XBTTracker:BonusHistory');
        $bonusHistory->user_id = $userId;
        $bonusHistory->points = $points;
        $bonusHistory->reason = $reason;
        $bonusHistory->date = \XF::$time;
        $bonusHistory->save();
        
        return true;
    }
    
    /**
     * Add a warning to a user
     * إضافة تحذير للمستخدم
     *
     * @param int $userId
     * @param string $reason
     * @return UserStatsEntity|bool
     */
    public function addWarning($userId, $reason)
    {
        $userStats = $this->getOrCreateUserStats($userId);
        
        $userStats->warnings++;
        $userStats->save();
        
        // Integration with XenForo warning system could be implemented here
        // يمكن تنفيذ تكامل مع نظام التحذيرات الخاص بـ XenForo هنا
        
        return $userStats;
    }
    
    /**
     * Check for Hit and Run violations
     * التحقق من مخالفات Hit and Run
     *
     * @return array List of warned users
     */
    public function checkHitAndRun()
    {
        $db = $this->db();
        $hitAndRunHours = \XF::options()->xbtTrackerHitAndRunHours;
        
        if (!$hitAndRunHours) {
            return [];
        }
        
        // Get completed torrents where user stopped seeding before minimum time
        // الحصول على التورنتات المكتملة حيث توقف المستخدم عن البذر قبل الوقت الأدنى
        $minSeedTime = \XF::$time - ($hitAndRunHours * 3600);
        
        $completedTorrents = $this->finder('Harment\XBTTracker:UserCompleted')
            ->with(['User', 'Torrent'])
            ->where('hit_and_run', 0)
            ->where('date', '<', $minSeedTime)
            ->where('seeded_until', 0)
            ->fetch();
            
        $warnedUsers = [];
        
        foreach ($completedTorrents as $completed) {
            // Check if user is still seeding
            // التحقق مما إذا كان المستخدم لا يزال يقوم بالبذر
            $isSeeding = $this->isUserSeeding($completed->user_id, $completed->torrent_id);
            
            if (!$isSeeding) {
                // User has hit and run
                // المستخدم قام بـ hit and run
                $completed->hit_and_run = 1;
                $completed->save();
                
                // Increment user warnings
                // زيادة تحذيرات المستخدم
                $userStats = $this->getUserStats($completed->user_id);
                if ($userStats) {
                    $userStats->warnings++;
                    $userStats->save();
                    
                    // Send warning to user
                    // إرسال تحذير للمستخدم
                    $this->sendHitAndRunWarning($completed->user_id, $completed->Torrent);
                    
                    $warnedUsers[] = $completed->user_id;
                }
            } else {
                // User is still seeding, update seeded_until time
                // المستخدم يقوم بالبذر، تحديث وقت seeded_until
                $completed->seeded_until = \XF::$time;
                $completed->save();
            }
        }
        
        return $warnedUsers;
    }
    
    /**
     * Check if a user is seeding a torrent
     * التحقق مما إذا كان المستخدم يقوم ببذر تورنت
     *
     * @param int $userId
     * @param int $torrentId
     * @return bool
     */
    public function isUserSeeding($userId, $torrentId)
    {
        $peer = $this->finder('Harment\XBTTracker:Peer')
            ->where([
                'user_id' => $userId,
                'torrent_id' => $torrentId,
                'seeder' => 1
            ])
            ->fetchOne();
            
        return ($peer !== null);
    }
    
    /**
     * Send Hit and Run warning notification to a user
     * إرسال تحذير Hit and Run للمستخدم
     *
     * @param int $userId
     * @param TorrentEntity $torrent
     * @return bool
     */
    protected function sendHitAndRunWarning($userId, TorrentEntity $torrent)
    {
        /** @var \XF\Repository\User $userRepo */
        $userRepo = $this->repository('XF:User');
        $user = $userRepo->getUserById($userId);
        
        if (!$user) {
            return false;
        }
        
        /** @var \XF\Service\User\TempChange $notifier */
        $notifier = $this->service('XF:User\TempChange', $user);
        
        $notifier->setNotification('xbt_hit_and_run_warning', [
            'title' => $torrent->title,
            'link' => \XF::app()->router('public')->buildLink('torrents', $torrent)
        ]);
        
        $notifier->notify();
        
        return true;
    }
    
    /**
     * Get list of top seeders
     * الحصول على قائمة أفضل البذارين
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\AbstractCollection
     */
    public function getTopSeeders($limit = 10)
    {
        return $this->finder('Harment\XBTTracker:UserStats')
            ->with('User')
            ->where('active_seeds', '>', 0)
            ->order('active_seeds', 'DESC')
            ->limit($limit)
            ->fetch();
    }
    
    /**
     * Get list of top uploaders
     * الحصول على قائمة أفضل الناقلين
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\AbstractCollection
     */
    public function getTopUploaders($limit = 10)
    {
        return $this->finder('Harment\XBTTracker:UserStats')
            ->with('User')
            ->where('uploaded', '>', 0)
            ->order('uploaded', 'DESC')
            ->limit($limit)
            ->fetch();
    }
    
    /**
     * Get users with largest share ratio
     * الحصول على المستخدمين ذوي أكبر نسبة مشاركة
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\AbstractCollection
     */
    public function getBestRatios($limit = 10)
    {
        // This is tricky because we need to calculate ratios
        // and users with 0 downloaded should have infinite ratio
        
        return $this->finder('Harment\XBTTracker:UserStats')
            ->with('User')
            ->where('downloaded', '>', 0)
            ->where('uploaded', '>', 0)
            ->order('uploaded / downloaded', 'DESC')
            ->limit($limit)
            ->fetch();
    }
    
    /**
     * Get users with most bonus points
     * الحصول على المستخدمين ذوي أكبر عدد من نقاط المكافأة
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\AbstractCollection
     */
    public function getTopBonusUsers($limit = 10)
    {
        return $this->finder('Harment\XBTTracker:UserStats')
            ->with('User')
            ->where('bonus_points', '>', 0)
            ->order('bonus_points', 'DESC')
            ->limit($limit)
            ->fetch();
    }
}