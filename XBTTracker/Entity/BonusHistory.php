<?php
// src/addons/XBTTracker/Entity/BonusHistory.php
namespace Harment\XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * Bonus History Entity
 * Stores history of bonus points earned and spent by users
 * 
 * كيان تاريخ المكافآت
 * يخزن تاريخ نقاط المكافآت التي اكتسبها المستخدمون وأنفقوها
 *
 * @property int $bonus_id
 * @property int $user_id
 * @property int $points
 * @property string $reason
 * @property int $date
 *
 * @property-read \XF\Entity\User $User
 */
class BonusHistory extends Entity
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
        $structure->table = 'xf_xbt_user_bonus_history';
        $structure->shortName = 'XBTTracker:BonusHistory';
        $structure->primaryKey = 'bonus_id';
        
        $structure->columns = [
            'bonus_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'points' => ['type' => self::INT, 'required' => true],
            'reason' => ['type' => self::STR, 'default' => ''],
            'date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];
        
        $structure->getters = [
            'formatted_points' => true,
            'is_positive' => true,
            'formatted_date' => true,
            'reason_phrase' => true
        ];
        
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ]
        ];
        
        return $structure;
    }

    /**
     * Get formatted points with sign
     * الحصول على النقاط المنسقة مع العلامة (+ أو -)
     * 
     * @return string
     */
    public function getFormattedPoints()
    {
        $sign = $this->points >= 0 ? '+' : '';
        return $sign . number_format($this->points);
    }

    /**
     * Check if this is a positive transaction (earning points)
     * فحص ما إذا كانت هذه معاملة إيجابية (كسب نقاط)
     * 
     * @return bool
     */
    public function getIsPositive()
    {
        return $this->points > 0;
    }

    /**
     * Get formatted date
     * الحصول على التاريخ المنسق
     * 
     * @param string $format Date format
     * @return string
     */
    public function getFormattedDate($format = '')
    {
        if (!$format) {
            return \XF::language()->dateTime($this->date);
        }
        
        return date($format, $this->date);
    }

    /**
     * Get reason as a language phrase if available
     * الحصول على السبب كعبارة لغة إذا كانت متاحة
     * 
     * @return string
     */
    public function getReasonPhrase()
    {
        if (!$this->reason) {
            return '';
        }
        
        $phraseKey = 'xf_xbt_bonus_reason_' . $this->reason;
        
        if (\XF::phrase($phraseKey)->render() !== $phraseKey) {
            // Phrase exists
            return \XF::phrase($phraseKey);
        }
        
        // Use the raw reason
        return $this->reason;
    }
    
    /**
     * Add bonus points to a user and record the history
     * إضافة نقاط مكافأة للمستخدم وتسجيل التاريخ
     * 
     * @param int $userId User ID
     * @param int $points Amount of points (positive or negative)
     * @param string $reason Reason for the adjustment
     * @return BonusHistory
     * @throws \Exception
     */
    public static function addUserBonus($userId, $points, $reason = '')
    {
        if (!$userId || !$points) {
            throw new \InvalidArgumentException("User ID and points are required");
        }
        
        $db = \XF::db();
        
        try {
            $db->beginTransaction();
            
            // First update the user's bonus points
            $db->query("
                UPDATE xf_xbt_user_stats 
                SET bonus_points = bonus_points + ? 
                WHERE user_id = ?
            ", [$points, $userId]);
            
            if (!$db->affectedRows()) {
                // Create user stats record if it doesn't exist
                $db->query("
                    INSERT INTO xf_xbt_user_stats 
                    (user_id, bonus_points, uploaded_total, downloaded_total, ratio_watch) 
                    VALUES (?, ?, 0, 0, 0)
                ", [$userId, $points]);
            }
            
            // Then create the history entry
            $history = \XF::em()->create('XBTTracker:BonusHistory');
            $history->user_id = $userId;
            $history->points = $points;
            $history->reason = $reason;
            $history->date = \XF::$time;
            $history->save();
            
            $db->commit();
            
            return $history;
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get bonus history summary for a user
     * الحصول على ملخص تاريخ المكافآت للمستخدم
     * 
     * @param int $userId User ID
     * @param int $days Number of days to look back (0 for all time)
     * @return array
     */
    public static function getUserBonusSummary($userId, $days = 30)
    {
        $db = \XF::db();
        
        $timeLimit = $days > 0 ? \XF::$time - ($days * 86400) : 0;
        
        $result = $db->fetchRow("
            SELECT 
                SUM(CASE WHEN points > 0 THEN points ELSE 0 END) AS earned,
                SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) AS spent,
                SUM(points) AS net
            FROM xf_xbt_user_bonus_history
            WHERE user_id = ? AND date > ?
        ", [$userId, $timeLimit]);
        
        return [
            'earned' => intval($result['earned'] ?? 0),
            'spent' => intval($result['spent'] ?? 0),
            'net' => intval($result['net'] ?? 0)
        ];
    }
}