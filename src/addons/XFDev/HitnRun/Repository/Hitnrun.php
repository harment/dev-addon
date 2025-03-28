<?php

namespace XFDev\HitnRun\Repository;

use XF\Db\Exception;
use XF\Entity\User;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Repository;

class Hitnrun extends Repository
{

    /**
     * @var float|int
     */
    private $hnrMinimumSeedHours;
    private $hnrMinimumRatio;
    private $hnrCheckMethod;
    private $hnrDownloadTrigger;
    private $hnrTolerancePeriod;

    /**
     * @var \ArrayObject
     */
    private $options;


    /**
     * Hitnrun constructor.
     * @param Manager $em
     * @param $identifier
     */

    public function __construct(Manager $em, $identifier)
    {
        parent::__construct($em, $identifier);

        $this->options = \XF::options();
        $this->hnrCheckMethod = $this->options->xfdev_hintrun_check_method;
//            Convert to Seconds
        $this->hnrMinimumSeedHours = $this->options->xfdev_hitnrun_minimum_seed_hours * 3600;
        $this->hnrMinimumRatio = $this->options->xfdev_hitnrun_minimum_ratio;
//            Convert to bytes
        $this->hnrDownloadTrigger = $this->options->xfdev_hitnrun_download_trigger * 1048576;
//            Convert to Seconds
        $this->hnrTolerancePeriod = \XF::$time - ($this->options->xfdev_hitnrun_check_tolerance_period * 3600);

    }


    public function checkHitnRun()
    {
        $db = \XF::db();

        $db->beginTransaction();

        $excludeUsergroups = $this->options->xfdevHitnRunBypassUsergroups;

        $finalExcludeUsergroups = implode(",", $excludeUsergroups);

        $parts = [];
        foreach ($excludeUsergroups as $usergroup)
        {
            $hitParts[] = 'FIND_IN_SET(' . $db->quote($usergroup) .' , user.secondary_group_ids) = 0';
            $skipParts[] = 'FIND_IN_SET(' . $db->quote($usergroup) .' , user.secondary_group_ids) <> 0';
        }

        $hitQueryJoiner = '';
        $skipQueryJoiner = '';
        if (!empty($hitParts))
        {
            //Joiner for hit part
            $hitJoiner = ' AND ';
            $hitQueryJoiner = implode($hitJoiner,$hitParts);
        }

        if(!empty($skipParts))
        {
            //Joiner for skip part
            $skipJoiner = ' OR ';
            $skipQueryJoiner = implode($skipJoiner,$skipParts);
        }

        if ($this->hnrCheckMethod == 'seed_only') {

            //Hit Users which don't meet the seed only criteria
            try {
                $db->query("UPDATE xftt_peer peer
                                    LEFT JOIN xf_user user ON peer.user_id = user.user_id
                                        SET peer.hit='yes',
                                        peer.hnr_checked = 1,
                                        peer.hnr_last_checked = ? 
                                  WHERE (user.user_group_id NOT IN (". $finalExcludeUsergroups . ") AND ". $hitQueryJoiner .")
                                         AND peer.mtime < ? 
                                        AND peer.hit='no' 
                                        AND peer.hnr_checked=0 
                                        AND peer.downloaded > ? 
                                        AND ((peer.leechtime+peer.seedtime) < ?)",
                    [
                        \XF::$time,
                        $this->hnrTolerancePeriod,
                        $this->hnrDownloadTrigger,
                        $this->hnrMinimumSeedHours
                    ]);
            } catch (Exception $e) {
                \XF::logError($e->getMessage());
            }

            //Mark Peers skip for future checks for excluded user_groups
            if (!empty($finalExcludeUsergroups)) {
                try {
                    $db->query("UPDATE xftt_peer peer
                                    LEFT JOIN xf_user user ON peer.user_id = user.user_id
                                        SET peer.hit='no',
                                        peer.hnr_checked = 2,
                                        peer.hnr_last_checked = ? 
                                  WHERE (user.user_group_id IN (" . $finalExcludeUsergroups . ") OR ". $skipQueryJoiner .")
                                         AND peer.mtime < ? 
                                        AND peer.hit='no' 
                                        AND peer.hnr_checked=0 
                                        AND peer.downloaded > ? 
                                        AND ((peer.leechtime+peer.seedtime) < ?)",
                        [
                            \XF::$time,
                            $this->hnrTolerancePeriod,
                            $this->hnrDownloadTrigger,
                            $this->hnrMinimumSeedHours,
                        ]);
                } catch (Exception $e) {
                    \XF::logError($e->getMessage());
                }
            }

        } elseif ($this->hnrCheckMethod == 'ratio_only') {

            //Hit users who don't meet the ratio only criteria
            try {
                $db->query("UPDATE xftt_peer peer
                                    LEFT JOIN xf_user user ON peer.user_id = user.user_id
                                        SET peer.hit='yes',
                                        peer.hnr_checked = 1,
                                        peer.hnr_last_checked = ? 
                                  WHERE (user.user_group_id NOT IN (" . $finalExcludeUsergroups . ") AND ". $hitQueryJoiner .")
                                         AND peer.mtime < ? 
                                        AND peer.hit='no' 
                                        AND peer.hnr_checked=0 
                                        AND peer.downloaded > ? 
                                        AND ((peer.uploaded/peer.downloaded) < ?)",
                    [
                        \XF::$time,
                        $this->hnrTolerancePeriod,
                        $this->hnrDownloadTrigger,
                        $this->hnrMinimumRatio
                    ]);
            } catch (Exception $e) {
                \XF::logError($e->getMessage());
            }

            //Mark peers skip for future checks for excluded user_groups
            if (!empty($finalExcludeUsergroups)) {
                try {
                    $db->query("UPDATE xftt_peer peer
                                    LEFT JOIN xf_user user ON peer.user_id = user.user_id
                                        SET peer.hit='no',
                                        peer.hnr_checked = 2,
                                        peer.hnr_last_checked = ? 
                                  WHERE (user.user_group_id IN (" . $finalExcludeUsergroups . ") OR ". $skipQueryJoiner .")
                                         AND peer.mtime < ? 
                                        AND peer.hit='no' 
                                        AND peer.hnr_checked=0 
                                        AND peer.downloaded > ? 
                                        AND ((peer.uploaded/peer.downloaded) < ?)",
                        [
                            \XF::$time,
                            $this->hnrTolerancePeriod,
                            $this->hnrDownloadTrigger,
                            $this->hnrMinimumRatio,
                        ]);
                } catch (Exception $e) {
                    \XF::logError($e->getMessage());
                }
            }

        } elseif ($this->hnrCheckMethod == 'seed_or_ratio') {
            //Hit Users if they don't meet either seedtime or ratio criteria
            try {
                $db->query("UPDATE xftt_peer peer
                                    LEFT JOIN xf_user user ON peer.user_id = user.user_id
                                        SET peer.hit='yes',
                                        peer.hnr_checked = 1,
                                        peer.hnr_last_checked = ? 
                                  WHERE (user.user_group_id NOT IN (" . $finalExcludeUsergroups . ") AND ". $hitQueryJoiner .")
                                         AND peer.mtime < ? 
                                        AND peer.hit='no' 
                                        AND peer.hnr_checked=0 
                                        AND peer.downloaded > ? 
                                        AND ((peer.uploaded/peer.downloaded) < ? AND (peer.leechtime+peer.seedtime) < ?)",
                    [
                        \XF::$time,
                        $this->hnrTolerancePeriod,
                        $this->hnrDownloadTrigger,
                        $this->hnrMinimumRatio,
                        $this->hnrMinimumSeedHours,
                    ]);
            } catch (Exception $e) {
                \XF::logError($e->getMessage());
            }

            //Mark peers skip from future checks for excluded user_groups
            if (!empty($finalExcludeUsergroups)) {
                try {
                    $db->query("UPDATE xftt_peer peer
                                    LEFT JOIN xf_user user ON peer.user_id = user.user_id
                                        SET peer.hit='no',
                                        peer.hnr_checked = 2,
                                        peer.hnr_last_checked = ? 
                                  WHERE (user.user_group_id IN (" . $finalExcludeUsergroups . ") OR ". $skipQueryJoiner .")
                                         AND peer.mtime < ? 
                                        AND peer.hit='no' 
                                        AND peer.hnr_checked=0 
                                        AND peer.downloaded > ? 
                                        AND ((peer.uploaded/peer.downloaded) < ? AND (peer.leechtime+peer.seedtime) < ?)",
                        [
                            \XF::$time,
                            $this->hnrTolerancePeriod,
                            $this->hnrDownloadTrigger,
                            $this->hnrMinimumRatio,
                            $this->hnrMinimumSeedHours,
                        ]);
                } catch (Exception $e) {
                    \XF::logError($e->getMessage());
                }
            }

        } elseif ($this->hnrCheckMethod == 'seed_and_ratio') {

            //Hit users if they don't meet the ratio and seedtime criteria both
            try {
                $db->query("UPDATE xftt_peer peer
                                    LEFT JOIN xf_user user ON peer.user_id = user.user_id
                                        SET peer.hit='yes',
                                        peer.hnr_checked = 1,
                                        peer.hnr_last_checked = ? 
                                  WHERE (user.user_group_id NOT IN (" . $finalExcludeUsergroups . ") AND ". $hitQueryJoiner .")
                                         AND peer.mtime < ? 
                                        AND peer.hit='no' 
                                        AND peer.hnr_checked=0 
                                        AND peer.downloaded > ? 
                                        AND ((peer.uploaded/peer.downloaded) < ? OR (peer.leechtime+peer.seedtime) < ?)",
                    [
                        \XF::$time,
                        $this->hnrTolerancePeriod,
                        $this->hnrDownloadTrigger,
                        $this->hnrMinimumRatio,
                        $this->hnrMinimumSeedHours,
                    ]);
            } catch (Exception $e) {
                \XF::logError($e->getMessage());
            }

            //Mark peers skip from future checks for excluded user_groups
            if (!empty($finalExcludeUsergroups)) {
                try {
                    $db->query("UPDATE xftt_peer peer
                                    LEFT JOIN xf_user user ON peer.user_id = user.user_id
                                        SET peer.hit='no',
                                        peer.hnr_checked = 2,
                                        peer.hnr_last_checked = ? 
                                  WHERE (user.user_group_id IN (" . $finalExcludeUsergroups . ") OR ". $skipQueryJoiner .")
                                         AND peer.mtime < ? 
                                        AND peer.hit='no' 
                                        AND peer.hnr_checked=0 
                                        AND peer.downloaded > ? 
                                        AND ((peer.uploaded/peer.downloaded) < ? OR (peer.leechtime+peer.seedtime) < ?)",
                        [
                            \XF::$time,
                            $this->hnrTolerancePeriod,
                            $this->hnrDownloadTrigger,
                            $this->hnrMinimumRatio,
                            $this->hnrMinimumSeedHours,
                        ]);
                } catch (Exception $e) {
                    \XF::logError($e->getMessage());
                }
            }
        }

        $db->commit();
    }

    //Set the hnr_checked = 0, if he starts a torrent again after zapping it
    public function recheckZappedTorrents()
    {

        $db = \XF::db();
        $db->beginTransaction();

        try {
            $db->query("UPDATE xftt_peer
                                        SET hnr_checked = 0
                                  WHERE mtime < ? 
                                        AND hit='no' 
                                        AND hnr_checked=3 
                                        AND downloaded > ? 
                                        AND downloaded > uploaded
                                        AND hnr_last_checked < mtime",
                [
                    $this->hnrTolerancePeriod,
                    $this->hnrDownloadTrigger
                ]);
        } catch (Exception $e) {
            \XF::logError($e->getMessage());
        }

        $db->commit();

    }

    //    Check for Hit Torrents and unhit them, if they satisfy requirements now.
    public function recheckHitPeers()
    {
        $db = \XF::db();

        $db->beginTransaction();

        if ($this->hnrCheckMethod == 'seed_only') {

            //UnHit Users which meet the seed only criteria
            try {
                $db->query("UPDATE xftt_peer
                                        SET hit='no',
                                        hnr_checked = 0,
                                        hnr_last_checked = ?
                                  WHERE                                   
                                        hit='yes' 
                                        AND hnr_checked = 1
                                        AND mtime > hnr_last_checked
                                        AND ((leechtime+seedtime) > ?)",
                    [
                        \XF::$time,
                        $this->hnrMinimumSeedHours
                    ]);
            } catch (Exception $e) {
                \XF::logError($e->getMessage());
            }

        } elseif ($this->hnrCheckMethod == 'ratio_only') {

            //UnHit users who meet the ratio only criteria
            try {
                $db->query("UPDATE xftt_peer peer
                                     SET hit='no',
                                        hnr_checked = 0,
                                        hnr_last_checked = ? 
                                  WHERE                                   
                                        hit='yes' 
                                        AND hnr_checked = 1
                                        AND mtime > hnr_last_checked
                                        AND ((peer.uploaded/peer.downloaded) > ?)",
                    [
                        \XF::$time,
                        $this->hnrMinimumRatio
                    ]);
            } catch (Exception $e) {
                \XF::logError($e->getMessage());
            }

        } elseif ($this->hnrCheckMethod == 'seed_or_ratio') {
            //UnHit Users if they meet either seedtime or ratio criteria
            try {
                $db->query("UPDATE xftt_peer peer
                                     SET hit='no',
                                        hnr_checked = 0,
                                        hnr_last_checked = ? 
                                  WHERE                                   
                                        hit='yes' 
                                        AND hnr_checked = 1
                                        AND mtime > hnr_last_checked
                                        AND ((peer.uploaded/peer.downloaded) > ? OR (peer.leechtime+peer.seedtime) > ?)",
                    [
                        \XF::$time,
                        $this->hnrMinimumRatio,
                        $this->hnrMinimumSeedHours,
                    ]);
            } catch (Exception $e) {
                \XF::logError($e->getMessage());
            }

        } elseif ($this->hnrCheckMethod == 'seed_and_ratio') {

            //UnHit users if they meet the ratio and seedtime criteria both
            try {
                $db->query("UPDATE xftt_peer peer
                                    SET hit='no',
                                        hnr_checked = 0,
                                        hnr_last_checked = ? 
                                  WHERE                                   
                                        hit='yes' 
                                        AND hnr_checked = 1
                                        AND mtime > hnr_last_checked
                                        AND ((peer.uploaded/peer.downloaded) > ? AND (peer.leechtime+peer.seedtime) > ?)",
                    [
                        \XF::$time,
                        $this->hnrMinimumRatio,
                        $this->hnrMinimumSeedHours,
                    ]);
            } catch (Exception $e) {
                \XF::logError($e->getMessage());
            }
        }

        $db->commit();
    }

    public function disableTorrentDownloadAccess()
    {

        $blockLeech = $this->options->xfdev_hitnrun_block_leech['xendevHitnRunBlockLeechEnabled'];
        if ($blockLeech) {
            $db = \XF::db();
            $minHnrs = $this->options->xfdev_hitnrun_block_leech['disableLeech_hnr'];
            $tolerancePeriod = \XF::$time - $this->options->xfdev_hitnrun_block_leech['disableLeech_tolerance'] * 3600;

            $users = $db->query("SELECT count(xftt_peer.id) AS hnrs,xftt_peer.user_id, xf_user.can_leech 
                                                FROM xftt_peer 
                                                LEFT JOIN xf_user ON xftt_peer.user_id = xf_user.user_id 
                                                WHERE xftt_peer.hit='yes' AND xftt_peer.hnr_checked=1 AND xftt_peer.hnr_last_checked < ? 
                                                GROUP BY xftt_peer.user_id", $tolerancePeriod)->fetchAll();

            //Disable Download Access
            if (!empty($users)) {
                $messageEnabled = $this->options->xfdev_hitnrun_send_conversation_on_block_leech['messageEnabled'];
                foreach ($users as $user) {

                    if ($user['hnrs'] >= $minHnrs && $user['can_leech'] === 1) {
                        try {
                            /**
                             * @var User $user
                             */
                            $user = $this->finder('XF:User')->where('user_id', $user['user_id'])->fetchOne();
                            $user->can_leech = 0;
                            $user->save();
                        } catch (\Exception $e) {
                            \XF::logError($e->getMessage());
                        }

                        if ($messageEnabled) {
                            $this->sendMessage($user['user_id']);
                        }
                    }
                }
            }
        }

    }

    public function enableTorrentDownloadAccess()
    {
        $minHnrs = $this->options->xfdev_hitnrun_block_leech['disableLeech_hnr'];


        $db = \XF::db();
        $downDisabledUsers = $db->query("SELECT xftt.user_id, 
                COUNT(IF(xftt.hit='yes', xftt.id, NULL)) AS hnrs, 
                u.can_leech FROM xftt_peer xftt 
                    LEFT JOIN xf_user u ON xftt.user_id=u.user_id
                WHERE u.can_leech=0
                GROUP BY xftt.user_id
                HAVING hnrs < ?",[
                    $minHnrs
        ])->fetchAll();

        //Enable Download Access
        foreach ($downDisabledUsers as $user) {
            try {
                /**
                 * @var User $user
                 */
                $user = $this->finder('XF:User')->where('user_id','=', $user['user_id'])->fetchOne();
                $user->can_leech = 1;
                $user->save();
            } catch (\Exception $e) {
                \XF::logError($e->getMessage());
            }
        }
    }


    /**
     * @var User
     */
    protected $user;
    /**
     * @var \XF\App|null
     */
    private $app;

    public function sendMessage($userId)
    {
        $options = \XF::options()->xfdev_hitnrun_send_conversation_on_block_leech;

        if ($options['messageEnabled']) {
            $this->user = \XF::finder('XF:User')->where('user_id', $userId)->fetchOne();
            $this->app = \XF::app();

            $participants = $options['messageParticipants'];
            if (!is_array($participants)) {
                \XF::logError('Cannot send torrent leech blocked message as there are no valid participants to send the message from.');
                return;
            }

            $starter = array_shift($participants);

            $starterUser = null;
            if ($starter) {
                /** @var User $starterUser */
                $starterUser = \XF::em()->find('XF:User', $starter);
            }
            if (!$starterUser) {
                \XF::logError('Cannot send torrent leech blocked message as there are no valid participants to send the message from.');
                return;
            }

            $tokens = $this->prepareTokens(false);
            $language = $this->app->language($this->user->language_id);

            $title = $this->replacePhrases($this->replaceTokens($options['messageTitle'], $tokens), $language);
            $body = $this->replacePhrases($this->replaceTokens($options['messageBody'], $tokens), $language);

            $recipients = [];
            if ($participants) {
                $recipients = \XF::em()->findByIds('XF:User', $participants)->toArray();
            }
            $recipients[$this->user->user_id] = $this->user;

            /** @var \XF\Service\Conversation\Creator $creator */
            $creator = \XF::service('XF:Conversation\Creator', $starterUser);
            $creator->setIsAutomated();
            $creator->setOptions([
                'open_invite' => $options['messageOpenInvite'],
                'conversation_open' => !$options['messageLocked']
            ]);
            $creator->setRecipientsTrusted($recipients);
            $creator->setContent($title, $body);
            if (!$creator->validate($errors)) {
                return;
            }
            $creator->setAutoSendNotifications(false);
            $conversation = $creator->save();

            /** @var \XF\Repository\Conversation $conversationRepo */
            $conversationRepo = $this->app->repository('XF:Conversation');
            $convRecipients = $conversation->getRelationFinder('Recipients')->with('ConversationUser')->fetch();

            $recipientState = ($options['messageDelete'] == 'delete_ignore' ? 'deleted_ignored' : 'deleted');

            /** @var \XF\Entity\ConversationRecipient $recipient */
            foreach ($convRecipients as $recipient) {
                if ($recipient->user_id == $this->user->user_id) {
                    continue;
                }

                $conversationRepo->markUserConversationRead($recipient->ConversationUser);

                if ($options['messageDelete'] != 'no_delete') {
                    $recipient->recipient_state = $recipientState;
                    $recipient->save();
                }
            }

            /** @var \XF\Service\Conversation\Notifier $notifier */
            $notifier = \XF::service('XF:Conversation\Notifier', $conversation);
            $notifier->addNotificationLimit($this->user)->notifyCreate();

//            $this->sentMessage = $conversation;
        }
    }

    protected function prepareTokens($escape = true)
    {
        $tokens = [
            '{name}' => $this->user->username,
            '{email}' => $this->user->email,
            '{id}' => $this->user->user_id
        ];

        if ($escape) {
            array_walk($tokens, function (&$value) {
                if (is_string($value)) {
                    $value = htmlspecialchars($value);
                }
            });
        }

        return $tokens;
    }

    protected function replaceTokens($string, array $tokens)
    {
        return strtr($string, $tokens);
    }

    protected function replacePhrases($string, \XF\Language $language)
    {
        return $this->app->stringFormatter()->replacePhrasePlaceholders($string, $language);
    }
}
