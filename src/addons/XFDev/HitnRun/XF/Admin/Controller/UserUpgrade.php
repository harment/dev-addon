<?php

namespace XFDev\HitnRun\XF\Admin\Controller;

class UserUpgrade extends XFCP_UserUpgrade
{
    protected function upgradeSaveProcess(\XF\Entity\UserUpgrade $upgrade)
    {
        $formAction = parent::upgradeSaveProcess($upgrade);
        $formAction->setup(function() use ($upgrade)
        {
            $upgrade->xfdev_hnr_reset = $this->filter('xfdev_hnr_reset', 'int') ;
        });
        return $formAction;
    }
}