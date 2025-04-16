<?php
// src/addons/XBTTracker/Repository/UserStats.php
namespace Harment\XBTTracker\Repository;

use XF\Mvc\Entity\Repository;

/**
 * مستودع إحصائيات المستخدم
 * يوفر طرق للوصول إلى وإدارة إحصائيات المستخدمين
 */
class UserStats extends Repository
{
    /**
     * الحصول على finder لإحصائيات المستخدم
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function getUserStatsFinder()
    {
        return $this->finder('XBTTracker:UserStats');
    }
    
    /**
     * العثور على إحصائيات المستخدم للقائمة
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findUserStatsForList()
    {
        return $this->getUserStatsFinder();
    }
    
    /**
     * الحصول على إحصائيات المستخدم حسب معرف المستخدم
     *
     * @param int $userId
     * @return \XBTTracker\Entity\UserStats|null
     */
    public function getUserStats($userId)
    {
        return $this->finder('XBTTracker:UserStats')
            ->where('user_id', $userId)
            ->fetchOne();
    }
    
    /**
     * الحصول على أو إنشاء إحصائيات المستخدم
     *
     * @param int $userId
     * @return \XBTTracker\Entity\UserStats
     */
    public function getOrCreateUserStats($userId)
    {
        $userStats = $this->getUserStats($userId);
        
        if (!$userStats) {
            /** @var \XBTTracker\Entity\UserStats $userStats */
            $userStats = $this->em()->create('XBTTracker:UserStats');
            $userStats->user_id = $userId;
            $userStats->passkey = $this->generatePasskey();
            $userStats->save();
        } else if (!$userStats->passkey) {
            // إذا كان مفتاح المرور فارغًا، قم بإنشاء واحد جديد
            $userStats->passkey = $this->generatePasskey();
            $userStats->save();
        }
        
        return $userStats;
    }
    
    /**
     * إنشاء مفتاح مرور فريد
     *
     * @return string
     */
    public function generatePasskey()
    {
        $passkey = bin2hex(random_bytes(20));
        
        // التحقق مما إذا كان مفتاح المرور موجود بالفعل
        $existing = $this->finder('XBTTracker:UserStats')
            ->where('passkey', $passkey)
            ->fetchOne();
        
        if ($existing) {
            // إذا كان موجودًا، قم بإنشاء واحد جديد
            return $this->generatePasskey();
        }
        
        return $passkey;
    }
    
    /**
     * تحديث إحصائيات المستخدم
     *
     * @param int $userId
     * @return bool
     */
    public function updateUserStats($userId)
    {
        $db = $this->db();
        
        // الحصول على عدد الأقران النشطين
        $stats = $db->fetchRow("
            SELECT
                SUM(IF(seeder = 1, 1, 0)) AS active_seeds,
                SUM(IF(seeder = 0, 1, 0)) AS active_leech
            FROM xf_xbt_peers
            WHERE user_id = ?
        ", [$userId]);
        
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
     * تحديث إحصائيات جميع المستخدمين
     *
     * @return bool
     */
    public function updateAllUserStats()
    {
        $db = $this->db();
        
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
     * منح نقاط مكافأة للمستخدم
     *
     * @param int $userId
     * @param int $points
     * @param string $reason
     * @return \XBTTracker\Entity\UserStats|bool
     */
    public function awardBonusPoints($userId, $points, $reason)
    {
        if ($points <= 0) {
            return false;
        }
        
        $userStats = $this->getOrCreateUserStats($userId);
        
        $userStats->bonus_points += $points;
        $userStats->save();
        
        // تسجيل منح نقاط المكافأة
        $this->recordBonusHistory($userId, $points, $reason);
        
        return $userStats;
    }
    
    /**
     * تسجيل تاريخ نقاط المكافأة
     *
     * @param int $userId
     * @param int $points
     * @param string $reason
     * @return bool
     */
    public function recordBonusHistory($userId, $points, $reason)
    {
        $bonusHistory = $this->em()->create('XBTTracker:BonusHistory');
        $bonusHistory->user_id = $userId;
        $bonusHistory->points = $points;
        $bonusHistory->reason = $reason;
        $bonusHistory->date = \XF::$time;
        $bonusHistory->save();
        
        return true;
    }
    
    /**
     * إضافة تحذير للمستخدم
     *
     * @param int $userId
     * @param string $reason
     * @return \XBTTracker\Entity\UserStats|bool
     */
    public function addWarning($userId, $reason)
    {
        $userStats = $this->getOrCreateUserStats($userId);
        
        $userStats->warnings++;
        $userStats->save();
        
        // يمكن تنفيذ تكامل مع نظام التحذيرات الخاص بـ XenForo هنا
        
        return $userStats;
    }
    
    /**
     * التحقق من مخالفات Hit and Run
     *
     * @return array قائمة المستخدمين الذين تم تحذيرهم
     */
    public function checkHitAndRun()
    {
        $db = $this->db();
        $hitAndRunHours = \XF::options()->xbtTrackerHitAndRunHours;
        
        if (!$hitAndRunHours) {
            return [];
        }
        
        // الحصول على التورنتات المكتملة حيث توقف المستخدم عن البذر قبل الوقت الأدنى
        $minSeedTime = \XF::$time - ($hitAndRunHours * 3600);
        
        $completedTorrents = $this->finder('XBTTracker:UserCompleted')
            ->with(['User', 'Torrent'])
            ->where('hit_and_run', 0)
            ->where('date', '<', $minSeedTime)
            ->where('seeded_until', 0)
            ->fetch();
            
        $warnedUsers = [];
        
        foreach ($completedTorrents as $completed) {
            // التحقق مما إذا كان المستخدم لا يزال يقوم بالبذر
            $isSeeding = $this->isUserSeeding($completed->user_id, $completed->torrent_id);
            
            if (!$isSeeding) {
                // المستخدم قام بـ hit and run
                $completed->hit_and_run = 1;
                $completed->save();
                
                // زيادة تحذيرات المستخدم
                $userStats = $this->getUserStats($completed->user_id);
                if ($userStats) {
                    $userStats->warnings++;
                    $userStats->save();
                    
                    // إرسال تحذير للمستخدم
                    $this->sendHitAndRunWarning($completed->user_id, $completed->Torrent);
                    
                    $warnedUsers[] = $completed->user_id;
                }
            } else {
                // المستخدم يقوم بالبذر، تحديث وقت seeded_until
                $completed->seeded_until = \XF::$time;
                $completed->save();
            }
        }
        
        return $warnedUsers;
    }
    
    /**
     * التحقق مما إذا كان المستخدم يقوم ببذر تورنت
     *
     * @param int $userId
     * @param int $torrentId
     * @return bool
     */
    public function isUserSeeding($userId, $torrentId)
    {
        $peer = $this->finder('XBTTracker:Peer')
            ->where([
                'user_id' => $userId,
                'torrent_id' => $torrentId,
                'seeder' => 1
            ])
            ->fetchOne();
            
        return ($peer !== null);
    }
    
    /**
     * إرسال تحذير Hit and Run للمستخدم
     *
     * @param int $userId
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool
     */
    protected function sendHitAndRunWarning($userId, \XBTTracker\Entity\Torrent $torrent)
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
     * الحصول على قائمة أفضل البذارين
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\AbstractCollection
     */
    public function getTopSeeders($limit = 10)
    {
        return $this->finder('XBTTracker:UserStats')
            ->with('User')
            ->where('active_seeds', '>', 0)
            ->order('active_seeds', 'DESC')
            ->limit($limit)
            ->fetch();
    }
    
    /**
     * الحصول على قائمة أفضل الناقلين
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\AbstractCollection
     */
    public function getTopUploaders($limit = 10)
    {
        return $this->finder('XBTTracker:UserStats')
            ->with('User')
            ->where('uploaded', '>', 0)
            ->order('uploaded', 'DESC')
            ->limit($limit)
            ->fetch();
    }
}