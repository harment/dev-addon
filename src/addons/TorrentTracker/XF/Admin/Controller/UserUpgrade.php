<?php

namespace TorrentTracker\XF\Admin\Controller;

class UserUpgrade extends XFCP_UserUpgrade
{
	protected function upgradeSaveProcess(\XF\Entity\UserUpgrade $upgrade)
	{ 
        $formAction = parent::upgradeSaveProcess($upgrade);
        $formAction->setup(function() use ($upgrade)
        {
            $upgrade->xentt_options = $this->filter('xentt_options', 'array-str') ;
        });
        return $formAction;  
	}
}