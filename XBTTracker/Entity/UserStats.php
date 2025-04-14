<?php
// src/addons/XBTTracker/Entity/UserStats.php
namespace XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

class UserStats extends \XF\Mvc\Entity\Entity
{
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
            'downloaded_formatted' => true
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
     * Calculate user ratio
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
     * Format uploaded amount
     *
     * @return string
     */
    public function getUploadedFormatted()
    {
        return \XF::language()->fileSizeFormat($this->uploaded);
    }
    
    /**
     * Format downloaded amount
     *
     * @return string
     */
    public function getDownloadedFormatted()
    {
        return \XF::language()->fileSizeFormat($this->downloaded);
    }
    
    /**
     * Generate passkey
     *
     * @return string
     */
    public function generatePasskey()
    {
        $this->passkey = bin2hex(random_bytes(16));
        return $this->passkey;
    }
    
    /**
     * Add bonus points
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
}
