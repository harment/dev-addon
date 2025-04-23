<?php

namespace Harment\XBTTracker\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class Account extends XFCP_Account
{
    /**
     * Extends the XF native account page to add XBTTracker-related account options
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionIndex()
    {
        $reply = parent::actionIndex();

        if ($reply instanceof \XF\Mvc\Reply\View)
        {
            $visitor = \XF::visitor();
            $xbtTrackerOptions = $visitor->getRelation('Option');

            if (!$xbtTrackerOptions)
            {
                $xbtTrackerOptions = $this->em()->create('Harment\XBTTracker:UserOptions');
                $xbtTrackerOptions->user_id = $visitor->user_id;
                $xbtTrackerOptions->save();
            }

            $reply->setParam('xbtTrackerOptions', $xbtTrackerOptions);
        }

        return $reply;
    }

    /**
     * Controller action to handle XBTTracker account preferences
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionXbtTracker()
    {
        $this->assertRegistrationRequired();

        $visitor = \XF::visitor();
        $xbtTrackerOptions = $visitor->getRelation('Option');

        if (!$xbtTrackerOptions)
        {
            $xbtTrackerOptions = $this->em()->create('Harment\XBTTracker:UserOptions');
            $xbtTrackerOptions->user_id = $visitor->user_id;
            $xbtTrackerOptions->save();
        }

        if ($this->isPost())
        {
            $passkey = $this->filter('passkey', 'str');
            if (empty($passkey))
            {
                // Generate a new passkey if empty
                $passkey = $this->generatePasskey($visitor);
            }

            // Update user options
            $xbtTrackerOptions->passkey = $passkey;
            $xbtTrackerOptions->save();

            return $this->redirect($this->buildLink('account/xbt-tracker'), 
                \XF::phrase('harment_xbttracker_preferences_have_been_updated'));
        }

        $viewParams = [
            'xbtTrackerOptions' => $xbtTrackerOptions
        ];

        return $this->view('Harment\XBTTracker:Account\XbtTracker', 'harment_xbttracker_account', $viewParams);
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

    /**
     * Handles regeneration of passkey
     *
     * @return \XF\Mvc\Reply\Redirect
     */
    public function actionRegeneratePasskey()
    {
        $this->assertRegistrationRequired();
        $this->assertPostOnly();

        $visitor = \XF::visitor();
        $xbtTrackerOptions = $visitor->getRelation('Option');

        if (!$xbtTrackerOptions)
        {
            $xbtTrackerOptions = $this->em()->create('Harment\XBTTracker:UserOptions');
            $xbtTrackerOptions->user_id = $visitor->user_id;
        }

        // Generate a new passkey
        $xbtTrackerOptions->passkey = $this->generatePasskey($visitor);
        $xbtTrackerOptions->save();

        return $this->redirect($this->buildLink('account/xbt-tracker'), 
            \XF::phrase('harment_xbttracker_passkey_has_been_regenerated'));
    }
}