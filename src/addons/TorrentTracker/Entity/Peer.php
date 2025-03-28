<?php

namespace TorrentTracker\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Peer extends Entity
{ 
	public function getRatio()
	{
		return $this->downloaded > 0 ? round(($this->uploaded / $this->downloaded), 2) : '-';
	}

	public function getTimespent()
	{
		$timespent = $this->seedtime + $this->leechtime;

        return $this->changeTimespentToReadableTime($timespent);

    }

    /*
     *  Added for Hit and Run Functionality
     * */
	public function getSeedTimespent()
    {
        $seedTimespent = $this->seedtime;

        return $this->changeTimespentToReadableTime($seedTimespent);

    }

    protected function changeTimespentToReadableTime($timespent)
    {
        switch($timespent)
        {
            case $timespent < 3600:
                $timespent = round(($timespent/60), 2) .' min';
                break;
            case $timespent < 86400:
                $timespent = round(($timespent/3600), 2) .' hrs';
                break;
            default:
                $timespent = round(($timespent/86400), 2) .' days';
                break;
        }
        return $timespent;
    }

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xftt_peer';
		$structure->shortName = 'TorrentTracker:Peer'; 
		$structure->primaryKey = 'id';
		$structure->columns = [
			'id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'torrent_id' => ['type' => self::UINT, 'default' => 0],
			'user_id' => ['type' => self::UINT, 'default' => 0],
			'active' => ['type' => self::UINT, 'default' => 0],
			'announced' => ['type' => self::UINT, 'default' => 0],
			'completed' => ['type' => self::UINT, 'default' => 0],
			'downloaded' => ['type' => self::STR, 'default' => 0],
			'uploaded' => ['type' => self::STR, 'default' => 0],
			'corrupt' => ['type' => self::UINT, 'default' => 0],
			'left' => ['type' => self::UINT, 'default' => 0],
			'leechtime' => ['type' => self::UINT, 'default' => 0],
			'seedtime' => ['type' => self::UINT, 'default' => 0],
			'mtime' => ['type' => self::UINT, 'default' => 0],
			'down_rate' => ['type' => self::UINT, 'default' => 0],
			'up_rate' => ['type' => self::UINT, 'default' => 0],
			'useragent' => ['type' => self::STR, 'maxLength' => 50],
			'peer_id' =>  ['type' => self::BINARY, 'maxLength' => 50],
			'ipa' =>  ['type' => self::UINT, 'default' => 0],
			 
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

		$structure->getters = [
			'ratio' => ['getter' => 'getRatio', 'cache' => false],
			'timespent' => ['getter' => 'getTimespent', 'cache' => false],
//            Added for Hit and Run functions
            'seed_timespent' => ['getter' => 'getSeedTimespent','cache' => false],
		];

		return $structure;
	}
	  
}
 