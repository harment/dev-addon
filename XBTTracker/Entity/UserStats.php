<?php
// src/addons/XBTTracker/Entity/UserStats.php
namespace Harment\XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
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
 * @property-read \XF\Mvc\Entity\ArrayCollection|\XBTTracker\Entity\BonusHistory[] $BonusHistory
 * @property-read \XF\Mvc\Entity\ArrayCollection|\XBTTracker\Entity\UserCompleted[] $CompletedTorrents
 */
class UserStats extends Entity
{
    /**
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
            'formatted_ratio' => true
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
            ]
        ];
        
        return $structure;
    }
    
    /**
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
     * الحصول على نسبة الرفع إلى التحميل بتنسيق مناسب للعرض
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
     * تنسيق حجم الملفات المرفوعة
     *
     * @return string
     */
    public function getUploadedFormatted()
    {
        return \XF::language()->fileSizeFormat($this->uploaded);
    }
    
    /**
     * تنسيق حجم الملفات المحملة
     *
     * @return string
     */
    public function getDownloadedFormatted()
    {
        return \XF::language()->fileSizeFormat($this->downloaded);
    }
    
    /**
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
        
        /** @var \XBTTracker\Entity\BonusHistory $bonusHistory */
        $bonusHistory = $this->em()->create('XBTTracker:BonusHistory');
        $bonusHistory->user_id = $this->user_id;
        $bonusHistory->points = $points;
        $bonusHistory->reason = $reason;
        $bonusHistory->date = \XF::$time;
        $bonusHistory->save();
        
        return $this->save();
    }
    
    /**
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
}