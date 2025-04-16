<?php
// src/addons/XBTTracker/Entity/BonusHistory.php
namespace Harment\XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

class BonusHistory extends \XF\Mvc\Entity\Entity
{
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
}
