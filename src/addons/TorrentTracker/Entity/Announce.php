<?php

namespace TorrentTracker\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Announce extends Entity
{ 
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xftt_announce_log';
		$structure->shortName = 'TorrentTracker:Announce'; 
		$structure->primaryKey = 'id';
		$structure->columns = [
			'id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'ipa' => ['type' => self::UINT, 'default' => 0],
			'port' => ['type' => self::UINT, 'default' => 0],
			'event' => ['type' => self::UINT, 'default' => 0],
			'info_hash' => ['type' => self::BINARY, 'default' => ''],
			'peer_id' => ['type' => self::BINARY, 'default' => ''],
			'downloaded' => ['type' => self::UINT, 'default' => 0],
			'left0' => ['type' => self::UINT, 'default' => 0],
			'uploaded' => ['type' => self::UINT, 'default' => 0],
			'uid' => ['type' => self::UINT, 'default' => 0],
			'mtime' => ['type' => self::UINT, 'default' => 0],
			 
		];


		return $structure;
	}
	  
}
 