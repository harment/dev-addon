<?php
// src/addons/XBTTracker/Entity/UserStats.php
namespace Harment\XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * User Statistics Entity
 * Tracks uploading, downloading, points and other statistics for each user
 * 
 * كيان إحصائيات المستخدم
 * يتتبع إحصائيات التحميل والرفع والنقاط وغيرها لكل مستخدم
 *
 * @property int $user_id
 * @property string $passkey
 * @property int $uploaded
 * @property int $downloaded
 * @property int $bonus_points
 * @property int $warnings
 * @property int $active_seeds
 * @property int $active_leech
 *
 * @property-read float $ratio
 * @property-read string $uploaded_formatted
 * @property-read string $downloaded_formatted
 * @property-read string $formatted_ratio
 *
 * @property-read \XF\Entity\User $User
 * @property-read \XF\Mvc\Entity\ArrayCollection|\Harment\XBTTracker\Entity\BonusHistory[] $BonusHistory
 * @property-read \XF\Mvc\Entity\ArrayCollection|\Harment\XBTTracker\Entity\UserCompleted[] $CompletedTorrents
 */
class UserStats extends Entity
{
    /**
     * Define the entity structure
     * تعريف هيكل الكيان
     *
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xbt_user_stats';
        $structure->shortName = 'XBTTracker:UserStats';
        $structure->primaryKey = 'user_id';
        
        $structure->columns = [
            'user_id' => ['type' => self::UINT, 'required' => true],
            'passkey' => ['type' => self::STR, 'maxLength' => 40, 'default' => ''],
            'uploaded' => ['type' => self::UINT, 'default' => 0],
            'downloaded' => ['type' => self::UINT, 'default' => 0],
            'bonus_points' => ['type' => self::UINT, 'default' => 0],
            'warnings' => ['type' => self::UINT, 'default' => 0],
            'active_seeds' => ['type' => self::UINT, 'default' => 0],
            'active_leech' => ['type' => self::UINT, 'default' => 0]
        ];
        
        $structure->getters = [
            'ratio' => true,
            'uploaded_formatted' => true,
            'downloaded_formatted' => true,
            'formatted_ratio' => true,
            'completed_count' => true
        ];
        
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'BonusHistory' => [
                'entity' => 'XBTTracker:BonusHistory',
                'type' => self::TO_MANY,
                'conditions' => 'user_id',
                'key' => 'bonus_id',
                'order' => 'date DESC'
            ],
            'CompletedTorrents' => [
                'entity' => 'XBTTracker:UserCompleted',
                'type' => self::TO_MANY,
                'conditions' => 'user_id',
                'key' => 'completed_id'
            ],
            'ActivePeers' => [
                'entity' => 'XBTTracker:Peer',
                'type' => self::TO_MANY,
                'conditions' => 'user_id',
                'key' => 'peer_id'
            ]
        ];
        
        return $structure;
    }
    
    /**
     * Calculate upload to download ratio
     * حساب نسبة الرفع إلى التحميل (الريشيو)
     *
     * @return float
     */
    public function getRatio()
    {
        if ($this->downloaded == 0) {
            return $this->uploaded > 0 ? 999 : 0;
        }
        
        return round($this->uploaded / $this->downloaded, 2);
    }
    
    /**
     * Get formatted upload to download ratio for display
     * الحصول على نسبة الرفع إلى التحميل بتنسيق مناسب للعرض
     * Legacy function for backward compatibility
     * دالة للتوافق مع الكود القديم
     *
     * @return string
     */
    public function getFormattedRatio()
    {
        $ratio = $this->getRatio();
        
        if ($ratio >= 999) {
            return '∞';
        }
        
        return number_format($ratio, 2);
    }
    
    /**
     * Format uploaded file size
     * تنسيق حجم الملفات المرفوعة
     *
     * @return string
     */
    public function getUploadedFormatted()
    {
        return \XF::language()->fileSizeFormat($this->uploaded);
    }
    
    /**
     * Format downloaded file size
     * تنسيق حجم الملفات المحملة
     *
     * @return string
     */
    public function getDownloadedFormatted()
    {
        return \XF::language()->fileSizeFormat($this->downloaded);
    }
    
    /**
     * Legacy functions for backward compatibility
     * دوال بديلة للتوافق مع الكود القديم
     */
    public function getFormattedUploaded()
    {
        return $this->getUploadedFormatted();
    }
    
    public function getFormattedDownloaded()
    {
        return $this->getDownloadedFormatted();
    }
    
    /**
     * Generate a new passkey
     * إنشاء مفتاح مرور جديد
     *
     * @return string
     */
    public function generatePasskey()
    {
        $this->passkey = bin2hex(random_bytes(16));
        return $this->passkey;
    }
    
    /**
     * Add bonus points to the user
     * إضافة نقاط مكافأة للمستخدم
     *
     * @param int $points
     * @param string $reason
     * @return bool
     */
    public function addBonusPoints($points, $reason = '')
    {
        if ($points == 0) {
            return true;
        }
        
        $this->bonus_points += $points;
        
        /** @var \Harment\XBTTracker\Entity\BonusHistory $bonusHistory */
        $bonusHistory = $this->em()->create('XBTTracker:BonusHistory');
        $bonusHistory->user_id = $this->user_id;
        $bonusHistory->points = $points;
        $bonusHistory->reason = $reason;
        $bonusHistory->date = \XF::$time;
        $bonusHistory->save();
        
        return $this->save();
    }
    
    /**
     * Create user statistics for a user
     * إنشاء إحصائيات للمستخدم
     * 
     * @param int $userId
     * @return UserStats
     */
    public static function createForUser($userId)
    {
        $userStats = \XF::em()->create('XBTTracker:UserStats');
        $userStats->user_id = $userId;
        $userStats->generatePasskey();
        $userStats->save();
        
        return $userStats;
    }
    
    /**
     * Get the count of completed torrents for this user
     * الحصول على عدد التورنتات المكتملة للمستخدم
     *
     * @return int
     */
    public function getCompletedCount()
    {
        return $this->db()->fetchOne('
            SELECT COUNT(*)
            FROM xf_xbt_user_completed
            WHERE user_id = ?
        ', $this->user_id);
    }
    
    /**
     * Get current active torrent count
     * الحصول على عدد التورنتات النشطة حاليًا
     *
     * @return array
     */
    public function getActiveTorrentCounts()
    {
        return [
            'seeding' => $this->active_seeds,
            'leeching' => $this->active_leech,
            'total' => $this->active_seeds + $this->active_leech
        ];
    }
    
    /**
     * Update active torrent counts based on current peers
     * تحديث أعداد التورنتات النشطة بناءً على النظراء الحاليين
     *
     * @return bool
     */
    public function updateActiveCounts()
    {
        $db = $this->db();
        
        $counts = $db->fetchRow('
            SELECT 
                SUM(IF(seeder = 1, 1, 0)) AS seed_count,
                SUM(IF(seeder = 0, 1, 0)) AS leech_count
            FROM xf_xbt_peers
            WHERE user_id = ?
        ', $this->user_id);
        
        $this->active_seeds = intval($counts['seed_count'] ?? 0);
        $this->active_leech = intval($counts['leech_count'] ?? 0);
        
        return $this->save();
    }
    
    /**
     * Get the percentage of seeded torrents
     * الحصول على نسبة التورنتات المزروعة
     *
     * @return float
     */
    public function getSeedPercentage()
    {
        $completedCount = $this->getCompletedCount();
        
        if ($completedCount == 0) {
            return 0;
        }
        
        $activeSeedCount = $this->active_seeds;
        return round(($activeSeedCount / $completedCount) * 100, 2);
    }
    
    /**
     * Get hit and run count
     * الحصول على عدد حالات الضرب والهرب
     *
     * @return int
     */
    public function getHitAndRunCount()
    {
        return $this->db()->fetchOne('
            SELECT COUNT(*)
            FROM xf_xbt_user_completed
            WHERE user_id = ? AND hit_and_run = 1
        ', $this->user_id);
    }
    
    /**
     * Check if user meets minimum ratio requirements
     * التحقق مما إذا كان المستخدم يلبي متطلبات الحد الأدنى للنسبة
     *
     * @return bool
     */
    public function meetsMinimumRatio()
    {
        $minRatio = \XF::options()->xbtTrackerRequiredRatio ?? 0;
        
        if ($minRatio <= 0) {
            return true;
        }
        
        return ($this->getRatio() >= $minRatio);
    }
    
    /**
     * Get user's bonus point exchange history
     * الحصول على تاريخ تبادل نقاط المكافأة للمستخدم
     *
     * @param int $limit
     * @return array
     */
    public function getRecentBonusHistory($limit = 10)
    {
        return $this->db()->fetchAll('
            SELECT *
            FROM xf_xbt_user_bonus_history
            WHERE user_id = ?
            ORDER BY date DESC
            LIMIT ?
        ', [$this->user_id, $limit]);
    }
}