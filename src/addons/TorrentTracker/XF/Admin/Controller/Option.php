<?php

namespace TorrentTracker\XF\Admin\Controller;

class Option extends XFCP_Option
{ 

	public function actionUpdate()
	{

		$response = parent::actionUpdate();
		$isTorrentOption = $this->filter('xenTorrentTrackerOptions', 'int');
		$torrentOption = $this->filter(['options' => ['xenTorrentTracker' => 'array'] ]);
		$anonymousAnnounce =  $this->filter(['options' => ['xenTorrentPrivateFlag' => 'int'] ]);
		$freeleech =$this->filter(['options' => ['xenTorrentGlobalFreeleech' => 'int'] ]);
		$cloudfare =  $this->filter(['options' => ['xenTorrentCloudfare' => 'int'] ]);
		$globalMultiplier = $this->filter(['options' => ['xenGlobalMultiplier' => 'array']]);
		$announceInterval = $this->filter(['options' => ['xfttAnnounceInterval' => 'int']]);
		$seedbonusInterval = $this->filter(['options' => ['xfttSeedbonusInterval' => 'int']]);
		$seedbonusAmountPerInterval = $this->filter(['options' => ['xfttSeedbonusAmountPerInterval' => 'int']]);



		if ($isTorrentOption)
		{
			$this->getTrackerRepo()->updateTrackerOptions($torrentOption['options']['xenTorrentTracker']['port'], $anonymousAnnounce['options']['xenTorrentPrivateFlag'], $freeleech['options']['xenTorrentGlobalFreeleech'], $cloudfare['options']['xenTorrentCloudfare']);

			$this->getTrackerRepo()->updateAdvancedTrackerOptions($announceInterval['options']['xfttAnnounceInterval'],$seedbonusInterval['options']['xfttSeedbonusInterval'],$seedbonusAmountPerInterval['options']['xfttSeedbonusAmountPerInterval']);

			if($globalMultiplier['options']['xenGlobalMultiplier'])
			{
				$this->getTrackerRepo()->updateGlobalMultiplier($globalMultiplier['options']['xenGlobalMultiplier']['xenGlobalMultiplierEnabled'],$globalMultiplier['options']['xenGlobalMultiplier']['upload_multiplier'],$globalMultiplier['options']['xenGlobalMultiplier']['download_multiplier']);
			}else{
				$this->getTrackerRepo()->resetGlobalMultiplier();
			}
		}

		return $response;
	}

	protected function getTrackerRepo()
	{
		return $this->repository('TorrentTracker:Tracker');
	}
	 
}