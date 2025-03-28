<?php


namespace XFDev\HitnRun;

use XF\Mvc\Entity\Entity;

class Listener
{
    public static function peerEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['hit'] = ['type' => Entity::STR, 'default' => 'no'];
        $structure->columns['hnr_checked'] = ['type' => Entity::STR, 'default' => 'no'];
        $structure->columns['hnr_last_checked'] = ['type' => Entity::UINT, 'default' => 0];
    }

    public static function userUpgradeEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['xfdev_hnr_reset'] = ['type' => Entity::UINT, 'default' => 0];
    }
}