<?php

namespace Harment\XBTTracker\XF\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class User extends XFCP_User
{
    /**
     * Extends the user edit page to add XBTTracker-related user options
     *
     * @param ParameterBag $params
     * 
     * @return \XF\Mvc\Reply\View
     */
    public function actionEdit(ParameterBag $params)
    {
        $reply = parent::actionEdit($params);

        if ($reply instanceof \XF\Mvc\Reply\View && isset($reply->getParams()['user']))
        {
            $user = $reply->getParam('user');
            
            // Get or create XBTTracker user options
            $xbtTrackerOptions = $user->getRelation('XBTTrackerOptions');
            if (!$xbtTrackerOptions)
            {
                $xbtTrackerOptions = $this->em()->create('Harment\XBTTracker:UserOptions');
                $xbtTrackerOptions->user_id = $user->user_id;
                $xbtTrackerOptions->save();
            }
            
            // Get or create XBTTracker user stats
            $xbtTrackerStats = $user->getRelation('XBTTrackerStats');
            if (!$xbtTrackerStats)
            {
                $xbtTrackerStats = $this->em()->create('Harment\XBTTracker:UserStats');
                $xbtTrackerStats->user_id = $user->user_id;
                $xbtTrackerStats->save();
            }
            
            $reply->setParam('xbtTrackerOptions', $xbtTrackerOptions);
            $reply->setParam('xbtTrackerStats', $xbtTrackerStats);
        }

        return $reply;
    }
    
    /**
     * Extends the user save process to save XBTTracker-related user options
     * 
     * @param \XF\Entity\User $user
     * @param \XF\Admin\Controller\UserSaveProcess $process
     */
    protected function saveAdditionalData(\XF\Entity\User $user, \XF\Admin\Controller\UserSaveProcess $process)
    {
        parent::saveAdditionalData($user, $process);
        
        $input = $this->filter([
            'xbt_tracker' => [
                'passkey' => 'str',
                'bonus_points' => 'float',
                'can_download' => 'bool',
                'can_upload' => 'bool'
            ]
        ]);
        
        if ($input['xbt_tracker'])
        {
            // Get or create XBTTracker user options
            $xbtTrackerOptions = $user->getRelation('XBTTrackerOptions');
            if (!$xbtTrackerOptions)
            {
                $xbtTrackerOptions = $this->em()->create('Harment\XBTTracker:UserOptions');
                $xbtTrackerOptions->user_id = $user->user_id;
            }
            
            if (empty($input['xbt_tracker']['passkey']))
            {
                // Generate a new passkey if empty
                $input['xbt_tracker']['passkey'] = $this->generatePasskey($user);
            }
            
            $xbtTrackerOptions->passkey = $input['xbt_tracker']['passkey'];
            $xbtTrackerOptions->can_download = $input['xbt_tracker']['can_download'];
            $xbtTrackerOptions->can_upload = $input['xbt_tracker']['can_upload'];
            $xbtTrackerOptions->save();
            
            // Get or create XBTTracker user stats
            $xbtTrackerStats = $user->getRelation('XBTTrackerStats');
            if (!$xbtTrackerStats)
            {
                $xbtTrackerStats = $this->em()->create('Harment\XBTTracker:UserStats');
                $xbtTrackerStats->user_id = $user->user_id;
            }
            
            $xbtTrackerStats->bonus_points = $input['xbt_tracker']['bonus_points'];
            $xbtTrackerStats->save();
        }
    }
    
    /**
     * Generates a unique passkey for the user
     *
     * @param \XF\Entity\User $user
     * @return string
     */
    protected function generatePasskey(\XF\Entity\User $user)
    {
        $data = $user->user_id . $user->username . \XF::$time . \XF::generateRandomString(16);
        return md5($data);
    }
}