<?php


namespace XFDev\HitnRun\XF\Admin\Controller;

use XF\Db\Exception;

class User extends XFCP_User
{
    protected function userSaveProcess(\XF\Entity\User $user)
    {
        $parent = parent::userSaveProcess($user);

        if ($this->filter('reset_users_hnr', 'bool'))
        {
            try {
                $db = \XF::db();
                $db->query("UPDATE xftt_peer SET hit='no', hnr_checked = 2 WHERE user_id=?", $user->user_id);
            } catch (Exception $e) {
                \XF::logError($e->getMessage());
            }
        }

        return $parent;
    }
}