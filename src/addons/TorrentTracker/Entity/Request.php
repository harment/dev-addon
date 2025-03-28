<?php

namespace TorrentTracker\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Request extends Entity
{ 
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xftt_freeleech_request';
		$structure->shortName = 'TorrentTracker:Request'; 
		$structure->primaryKey = 'request_id';
		$structure->columns = [
			'request_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'torrent_id' => ['type' => self::UINT, 'default' => 0],
			'user_id' => ['type' => self::UINT, 'default' => 0],
			'open' => ['type' => self::UINT, 'default' => 0],
			'action' =>  ['type' => self::STR, 'default' => 'accept',
				'allowedValues' => ['accept', 'reject', '']
			],
			'date' => ['type' => self::BINARY, 'default' => ''],
			 
		];
		$structure->relations = [
			'Torrent' => [
					'entity' => 'TorrentTracker:Torrent',
					'type' => self::TO_ONE,
					'conditions' => 'torrent_id',
					'primary' => true,
					'with' => 'Thread',
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
 