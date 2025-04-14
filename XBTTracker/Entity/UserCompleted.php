<?php
// src/addons/XBTTracker/Entity/UserCompleted.php
namespace XBTTracker\Entity;

use XF\Mvc\Entity\Structure;

class UserCompleted extends \XF\Mvc\Entity\Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xbt_user_completed';
        $structure->shortName = 'XBTTracker:UserCompleted';
        $structure->primaryKey = 'completed_id';
        
        $structure->columns = [
            'completed_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'torrent_id' => ['type' => self::UINT, 'required' => true],
            'date' => ['type' => self::UINT, 'default' => \XF::$time],
            'seeded_until' => ['type' => self::UINT, 'default' => 0],
            'hit_and_run' => ['type' => self::BOOL, 'default' => false]
        ];
        
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Torrent' => [
                'entity' => 'XBTTracker:Torrent',
                'type' => self::TO_ONE,
                'conditions' => 'torrent_id',
                'primary' => true
            ]
        ];
        
        return $structure;
    }