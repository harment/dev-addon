<?php

namespace TorrentTracker\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Info extends Entity
{ 
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xftt_torrent_info';
		$structure->shortName = 'TorrentTracker:Info'; 
		$structure->primaryKey = 'torrent_id';
		$structure->columns = [
			'torrent_id' => ['type' => self::UINT],
			'file_details' => ['type' => self::JSON_ARRAY, 'default' => []],
		];

		return $structure;
	}
	  
}
 