<?php

namespace TorrentTracker\XF\Service\User;

class Upgrade extends XFCP_Upgrade
{ 
	public function upgrade()
	{
		
		$active = parent::upgrade();
		$user = $this->user;
		$upgrade = $this->userUpgrade;
		if (!$active || empty($upgrade['xentt_options']))
		{
			return $active;
		}
		$torrentOptions = $upgrade['xentt_options'];
		if ($active && !empty($torrentOptions))
		{
			$db = $this->db();
			$userInfo = $db->fetchRow('
				SELECT uploaded, wait_time, can_leech, freeleech, peers_limit,upload_multiplier,download_multiplier FROM xf_user
				WHERE user_id = ?
			', $user->user_id);
			$activeExtra = $active['extra'];
			$activeExtra += array(
				'torrentOptions' => $userInfo
			);
			$active->extra = $activeExtra;
			$active->save();

			$user->can_leech = (empty($torrentOptions['can_leech']) && $userInfo['can_leech'] == 0) ? 0 : 1;
			$user->freeleech = (empty($torrentOptions['freeleech']) && $userInfo['freeleech'] == 0) ? 0 : 1;
			$user->wait_time = ($userInfo['wait_time'] > 0) ? $torrentOptions['wait_time'] : 0;
			$user->peers_limit = ($userInfo['peers_limit'] > 0) ? $torrentOptions['peers_limit'] : 0;
			$user->uploaded = $userInfo['uploaded']+(intval($torrentOptions['upload_credit'])*1048576);
			$user->upload_multiplier = $torrentOptions['upload_multiplier'];
			$user->download_multiplier = $torrentOptions['download_multiplier'];
			$user->save();
		}

		return $active;
	}
}