<?php
// src/addons/XBTTracker/Entity/Peer.php
namespace XBTTracker\Entity;

use XF\Mvc\Entity\Structure;

class Peer extends \XF\Mvc\Entity\Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xbt_peers';
        $structure->shortName = 'XBTTracker:Peer';
        $structure->primaryKey = 'peer_id';
        
        $structure->columns = [
            'peer_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'torrent_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'peer_id_binary' => ['type' => self::BINARY, 'required' => true, 'maxLength' => 20],
            'ip' => ['type' => self::STR, 'required' => true, 'maxLength' => 45],
            'port' => ['type' => self::UINT, 'required' => true],
            'uploaded' => ['type' => self::UINT, 'default' => 0],
            'downloaded' => ['type' => self::UINT, 'default' => 0],
            'left_bytes' => ['type' => self::UINT, 'required' => true],
            'seeder' => ['type' => self::BOOL, 'required' => true],
            'first_announce' => ['type' => self::UINT, 'required' => true],
            'last_announce' => ['type' => self::UINT, 'required' => true],
            'completed' => ['type' => self::BOOL, 'default' => false],
            'hit_and_run_warned' => ['type' => self::BOOL, 'default' => false],
            'passkey' => ['type' => self::STR, 'required' => true, 'maxLength' => 40]
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
}
