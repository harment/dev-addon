<?php

namespace TorrentTracker\XF\Entity;

use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{

	public function canViewTorrents(&$error = null)
	{
		return $this->hasPermission('xenTorrentTracker', 'view');
	}

	public function canDownloadTorrent(&$error = null)
	{
		return ($this->user_id && $this->hasPermission('xenTorrentTracker', 'download'));
	}

	public function canUploadTorrent(&$error = null)
	{
		return $this->hasPermission('xenTorrentTracker', 'upload');
	}

	public function canViewPeerList(&$error = null)
	{
		return $this->hasPermission('xenTorrentTracker', 'viewPeerList');
	}

	public function canViewSnatchList(&$error = null)
	{
		return $this->hasPermission('xenTorrentTracker', 'viewSnatchList');
	}

	public function canAcceptFreeleechRequest(&$error = null)
	{
		return $this->hasPermission('xenTorrentTracker', 'canMakeFreeleech');
	}

	public function canMakeFreeleechRequest(&$error = null)
	{
		return $this->hasPermission('xenTorrentTracker', 'sentfreeleechrequest');
	}

	public function canStickUnstickTorrent(&$error = null)
	{
		return $this->hasPermission('xenTorrentTracker','stickUnstickTorrent');
	}

	protected function _preSave()
	{
		parent::_preSave(); 
		if($this->isInsert())
		{
			$option = \XF::options()->xenTTNewUser;

			$userInput = array(
				'downloaded' => 0,
				'uploaded'   => intval($option['upload']) * 1048576, // option * 1MB
				'wait_time'   => !empty($option['wait_time']) ? $option['wait_time'] : 0,
				'peers_limit'   => !empty($option['peers_limit']) ? $option['peers_limit'] : 0,
				'seedbonus'   => 0,
				'freeleech'   => 0,
				'can_leech'   => !empty($option['can_leech']) ? 1 : 0,
				'torrent_pass' => '',
				'torrent_pass_version' => 0,
				'upload_multiplier' => 1
			);

			$this->bulkSet($userInput);
		}
		 
	}

	public function getRatio()
	{
		return $this->downloaded > 0 ? round(($this->uploaded / $this->downloaded), 2) : '-';
	}

	public function getTorrentsCount()
    {
        return \XF::finder('TorrentTracker:Torrent')->where('user_id','=',$this->user_id)->total();
    }

	public function getSeedbonus()
    {
        return \XF::language()->numberFormat($this->seedbonus,0);
    }

	public function getTorrentStats()
    {
        $torrentStats['downloaded'] = $this->downloaded;
        $torrentStats['uploaded'] = $this->uploaded;
        $torrentStats['ratio'] = $this->getRatio();
        $torrentStats['seedbonus'] = $this->getSeedbonus();

        $torrentStats['currentUploadCount'] = $this->torrent_upload_count;
        $torrentStats['totalUploadCount'] =  $this->torrentsCount;

        return $torrentStats;
    }

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);
		$structure->getters['ratio'] = true;
		$structure->getters['torrentsCount'] = true;
		return $structure;
	}

	public function getSeedingTorrentIds(){
	    $peerRepo = $this->finder('TorrentTracker:Peer')->where('user_id',$this->user_id)
            ->where('active', 1)
            ->where('left', 0);

	    $peerRepo->pluckFrom('torrent_id');
    }
}