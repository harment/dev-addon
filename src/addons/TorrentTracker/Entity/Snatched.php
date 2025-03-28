<?php

namespace TorrentTracker\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Snatched extends Entity
{

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xftt_snatched';
		$structure->shortName = 'TorrentTracker:Snatched'; 
		$structure->primaryKey = 'user_id';
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'default' => 0],
			'mtime' => ['type' => self::UINT, 'default' => 0],
			'torrent_id' => ['type' => self::UINT, 'default' => 0],
			'ipa' => ['type' => self::UINT, 'default' => 0],
		];

		$structure->relations = [
			'Torrent' => [
				'entity' => 'TorrentTracker:Torrent',
				'type' => self::TO_ONE,
				'conditions' => 'torrent_id',
				'primary' => true,
			], 

			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			], 
		];
		return $structure;
	}
	  
}
 