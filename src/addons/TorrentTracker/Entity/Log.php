<?php

namespace TorrentTracker\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Log extends Entity
{ 
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xftt_log';
		$structure->shortName = 'TorrentTracker:Log';
		$structure->primaryKey = 'log_id';
		$structure->columns = [
			'log_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'log_date' => ['type' => self::UINT, 'default' => ''],
			'message' => ['type' => self::JSON, 'default' => []],
			'params' => ['type' => self::JSON_ARRAY, 'default' => []],
			'action' => ['type' => self::STR, 'default' => ''],
			'is_error' => ['type' => self::BINARY, 'default' => 0],
		];


		return $structure;
	}
	  
}
 