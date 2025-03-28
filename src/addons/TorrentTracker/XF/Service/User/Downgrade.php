<?php

namespace TorrentTracker\XF\Service\User;

class Downgrade extends XFCP_Downgrade
{
	public function downgrade()
	{ 
		$user = $this->user;
		$active = $this->activeUpgrade; 
		$db = $this->db();
		$db->beginTransaction();
		if ($active)
		{
			$extra = $active['extra'];
			if(isset($extra['torrentOptions']['uploaded']))
			{
				unset($extra['torrentOptions']['uploaded']);
			}
			
			if (!empty($extra['torrentOptions']))
			{
				$db->update('xf_user', $extra['torrentOptions'], 'user_id = ' . $db->quote($active['user_id']));
			}
		}

		$db->commit();

		return parent::downgrade();
	}
}