<?php

namespace TorrentTracker\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Torrent extends Entity
{

//    Set Torrent for update by xbt by setting flags=3
    protected function _preSave()
    {
        if ($this->isUpdate() && $this->isChanged('freeleech'))
        {
            $this->set('flags', 3);
        }
    }

    public function canView()
    {
        return $this->Thread->canView();
    }

	public function canRequestForFreeleech(&$error = null)
	{ 
		if ($this->Request)
		{
			return false;
		}
		if($this->freeleech == 0)
		{
			return true;
		}
		return false;
	}

	public function canAskForReseed(&$error = null)
	{
		$reseedInterval = \XF::options()->xenTorrentReseedInterval;
		if (($this['last_reseed_request'] + $reseedInterval) > \XF::$time)
		{
			return false;
		}
		 
		if ($this->seeders == 0 && \XF::$time > ($this->ctime + 86400))
		{
			return true;
		}

		return false;
	}

	public function getmagnetLink()
	{
		if (\XF::options()->xenTorrentMagnetLink)
		{
			$title = $this->Info->file_details->name ? $this->Info->file_details->name : '';
			return $this->getTorrentRepo()->getMagnetLink($this->info_hash, $title, $this->size);
		}
		return false;
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xftt_torrent';
		$structure->shortName = 'TorrentTracker:Torrent'; 
		$structure->primaryKey = 'torrent_id';
		$structure->columns = [
			'torrent_id' => ['type' => self::UINT, 'nullable' => true],
			'info_hash' => ['type' => self::BINARY, 'default' => ''],
			'category_id' => ['type' => self::UINT,  'default' => 0],
			'user_id' => ['type' => self::UINT, 'default' => 0],
			'thread_id' => ['type' => self::UINT, 'default' => 0],
			'leechers' => ['type' => self::UINT, 'default' => 0],
			'completed' => ['type' => self::UINT, 'default' => 0],
			'seeders' => ['type' => self::UINT, 'default' => 0],
			'flags' => ['type' => self::UINT, 'default' => 0],
			'mtime' => ['type' => self::UINT, 'default' => 0],
			'ctime' => ['type' => self::UINT, 'default' => \XF::$time],
			'size' => ['type' => self::STR, 'default' => 0],
			'number_files' => ['type' => self::UINT, 'default' => 0],
			'balance' => ['type' => self::UINT, 'default' => 0],
			'freeleech' => ['type' => self::UINT, 'default' => 0],
			'last_reseed_request' => ['type' => self::UINT, 'default' => 0],
			'upload_multiplier' => ['type' => self::UINT, 'default' => 1],
			'download_multiplier' => ['type' => self::UINT, 'default' => 1],
			'sticky' => ['type' => self::UINT, 'default' => 0],
		];
		$structure->getters = [ 
			'magnetLink' => ['getter' => 'getmagnetLink', 'cache' => false],
		];

		$structure->relations = [
			'Request' => [
				'entity' => 'TorrentTracker:Request',
				'type' => self::TO_ONE,
				'conditions' => 'torrent_id',
				'primary' => true,
			], 

			'Thread' => [
				'entity' => 'XF:Thread',
				'type' => self::TO_ONE,
				'conditions' => 'thread_id',
				'primary' => true,
				'with' => 'Forum', 
			], 
			
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			], 

			'Info' => [
				'entity' => 'TorrentTracker:Info',
				'type' => self::TO_ONE,
				'conditions' => 'torrent_id',
				'primary' => true,
			],

			'Attachment' => [
				'entity' => 'XF:Attachment',
				'type' => self::TO_ONE,
				'conditions' => [
					['attachment_id', '=', '$torrent_id']
				],
				'primary' => true,
				'with' => 'Data'
			],

		];


		return $structure;
	}
	protected function getTorrentRepo()
	{
		return $this->repository('TorrentTracker:Torrent');
	} 
	  
}