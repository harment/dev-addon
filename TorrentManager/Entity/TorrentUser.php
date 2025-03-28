<?php

namespace TorrentManager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class TorrentUser extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_torrent_user';
        $structure->shortName = 'TorrentManager:TorrentUser';
        $structure->primaryKey = ['user_id', 'torrent_id'];
        $structure->columns = [
            'user_id' => ['type' => self::UINT, 'required' => true],
            'torrent_id' => ['type' => self::UINT, 'required' => true],
            'uploaded' => ['type' => self::UINT, 'default' => 0],
            'downloaded' => ['type' => self::UINT, 'default' => 0],
            'ratio' => ['type' => self::FLOAT, 'default' => 0],
            'seed_time' => ['type' => self::UINT, 'default' => 0],
            'bonus_points' => ['type' => self::UINT, 'default' => 0],
            'warnings' => ['type' => self::UINT, 'default' => 0]
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Torrent' => [
                'entity' => 'TorrentManager:Torrent',
                'type' => self::TO_ONE,
                'conditions' => 'torrent_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}