<?php

namespace XFDev\HitnRun\XF\Service\User;

class Upgrade extends XFCP_Upgrade
{ 
	public function upgrade()
	{
		$active = parent::upgrade();
		$user = $this->user;
		$upgrade = $this->userUpgrade;
        $hnrOptions = $upgrade['xfdev_hnr_reset'];

        if (!$active || empty($hnrOptions))
		{
			return $active;
		}else
		{
			$db = $this->db();
			$db->query("UPDATE xftt_peer SET hit='no', hnr_checked = 2 WHERE user_id=?",$user->user_id);
		}

		return $active;
	}
}