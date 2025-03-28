<?php

namespace TorrentManager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Torrent extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_torrent';
        $structure->shortName = 'TorrentManager:Torrent';
        $structure->primaryKey = 'torrent_id';
        $structure->columns = [
            'torrent_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'title' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'category_id' => ['type' => self::UINT, 'required' => true],
            'file_path' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'hash' => ['type' => self::STR, 'maxLength' => 40, 'required' => true],
            'video_type' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
            'audio_type' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
            'audio_channels' => ['type' => self::STR, 'maxLength' => 10, 'required' => true],
            'poster_url' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'tmdb_link' => ['type' => self::STR, 'maxLength' => 255, 'default' => null],
            'upload_date' => ['type' => self::UINT, 'required' => true],
            'status' => ['type' => self::STR, 'allowedValues' => ['active', 'dead'], 'default' => 'active']
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ]
        ];
        $structure->defaultWith = ['User'];

        return $structure;
    }
}