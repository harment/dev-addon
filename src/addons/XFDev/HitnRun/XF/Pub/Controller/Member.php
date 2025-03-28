<?php


namespace XFDev\HitnRun\XF\Pub\Controller;


use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\PrintableException;

class Member extends XFCP_Member
{

    public function actionHitnrun(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);
        $visitor = \XF::visitor();
        $canViewOther = $visitor->hasPermission('xfdevHitandRunModerator', 'canSeeOthersHnr');
        $canDeleteOthersHnr = $visitor->hasPermission('xfdevHitandRunModerator', 'canDeleteOthersHnr');

        if (!$canViewOther) {
            return $this->noPermission();
        }

        $page = $params->page;
        $perPage = 10;

        $hitnrunRepo = $this->getHitnrunRepo();
        $hitnrunFinder = $hitnrunRepo->where('user_id', $user->user_id)->limitByPage($page, $perPage);

        $hitnruns = $hitnrunFinder->fetch();
        $hitnrunsCount = $hitnrunFinder->total();
        $viewParams = [
            'user' => $user,
            'hitnruns' => $hitnruns,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $hitnrunsCount,
        ];

        return $this->view('XF:Member\Hitnrun', 'xfdev_members_hitnrun', $viewParams);
    }

    public function actionHitnrunDelete(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);
        $peer = $this->filter('peer', 'uint');

        $visitor = \XF::visitor();
        $canViewOther = $visitor->hasPermission('xfdevHitandRunModerator', 'canSeeOthersHnr');
        $canDeleteOthersHnr = $visitor->hasPermission('xfdevHitandRunModerator', 'canDeleteOthersHnr');

        if (!$canViewOther && !$canDeleteOthersHnr) {
            return $this->noPermission();
        }

        $peerFinder = $this->finder('TorrentTracker:Peer')->where('user_id', $user->user_id)->where('id', $peer)->fetchOne();

        if (!$peerFinder) {
            throw $this->exception($this->notFound(\XF::phrase('this_peer_doesnt_exist_or_doesnt_belongs_to_this_user')));
        }

        if ($this->isPost()) {
            try {
                $peerFinder->hit = "no";
                $peerFinder->hnr_checked = 2;
                $peerFinder->save();
            } catch (\Exception $e) {
                \XF::logError($e->getMessage());
            }

            return $this->redirect($this->buildLink('members/hitnrun', $user), \XF::phrase('hitnrun_has_been_successfully_removed'));
        } else {
            $viewParams = [
                'peer' => $peer,
                'user' => $user
            ];
            return $this->view('', 'remove_users_hitnrun', $viewParams);
        }
    }

    public function actionMyHnr(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id) {
            throw $this->exception($this->notFound(\XF::phrase('requested_user_not_found')));
        }

        $finder = $this->getHitnrunRepo();
        $hnr = $finder->where('user_id', '=', $visitor->user_id);

        if($this->isPost())
        {
            $peer = $this->filter('peer', 'uint');
            $userPeer = $hnr->where('id', '=', $peer)
                ->fetchOne();

            if (!$userPeer) {
                throw $this->exception($this->notFound(\XF::phrase('this_peer_doesnt_exist_or_doesnt_belongs_to_you')));
            }

            $leftAmount = ($userPeer->downloaded - $userPeer->uploaded) * $this->options()->xfdev_hitnrun_zap_multiplier;
            $points = floor(($leftAmount / 1048576) * \XF::options()->xfdev_hitnrun_1mbtobonus);


            if($this->filter('upload','uint'))
            {
                if ($leftAmount > $visitor['uploaded']) {
                    throw $this->exception($this->notFound(\XF::phrase('you_dont_have_enough_upload_data')));
                }

                //Deduct the left amount from users account
                try {
                    $visitor->uploaded = $visitor['uploaded'] - $leftAmount;
                    $visitor->save();
                } catch (\Exception $e) {
                    \XF::logError($e->getMessage());
                }

                $this->zapHnr($userPeer, $leftAmount);

                $redirect = $this->buildLink('members/my-hnr', $visitor);
                return $this->redirect($redirect,'Hit & Run has been successfully Zapped');

            }elseif($this->filter('bonus','uint'))
            {
                if ($points > $visitor['seedbonus']) {
                    throw $this->exception($this->notFound(\XF::phrase('you_dont_have_enough_seedbonus')));
                }

                //Deduct the left amount from users account
                try {
                    $visitor->seedbonus = $visitor['seedbonus'] - $points;
                    $visitor->save();
                } catch (\Exception $e) {
                    \XF::logError($e->getMessage());
                }

                //Remove HNR and mark it as checked
                $this->zapHnr($userPeer, $leftAmount);

                $redirect = $this->buildLink('members/my-hnr', $visitor);
                return $this->redirect($redirect,'Hit & Run has been successfully Zapped');
            }
        }


//        if ($this->filter('zap', 'int')) {
//            $peer = $this->filter('peer', 'int');
//            $userPeer = $hnr->where('id', '=', $peer)->fetchOne();
//
//            if (!$userPeer) {
//                throw $this->exception($this->notFound(\XF::phrase('this_peer_doesnt_exist_or_doesnt_belongs_to_you')));
//            }
//
//            $leftAmount = ($userPeer->downloaded - $userPeer->uploaded) * $this->options()->xfdev_hitnrun_zap_multiplier;
//            $points = floor(($leftAmount / 1048576) * \XF::options()->xfdev_hitnrun_1mbtobonus);
//
//            if ($this->filter('bonus', 'uint')) {
//                if ($points > $visitor['seedbonus']) {
//                    throw $this->exception($this->notFound(\XF::phrase('you_dont_have_enough_seedbonus')));
//                }
//
//                //Deduct the left amount from users account
//                try {
//                    $visitor->seedbonus = $visitor['seedbonus'] - $points;
//                    $visitor->save();
//                } catch (\Exception $e) {
//                    \XF::logError($e->getMessage());
//                }
//
//                //Remove HNR and mark it as checked
//                $this->zapHnr($userPeer, $leftAmount);
//
//                $redirect = $this->buildLink('members/my-hnr', $visitor);
//                return $this->redirect($redirect);
//
//            } else if ($this->filter('upload', 'uint')) {
//                if ($leftAmount > $visitor['uploaded']) {
//                    throw $this->exception($this->notFound(\XF::phrase('you_dont_have_enough_upload_data')));
//                }
//
//                //Deduct the left amount from users account
//                try {
//                    $visitor->uploaded = $visitor['uploaded'] - $leftAmount;
//                    $visitor->save();
//                } catch (\Exception $e) {
//                    \XF::logError($e->getMessage());
//                }
//
//                $this->zapHnr($userPeer, $leftAmount);
//
//                $redirect = $this->buildLink('members/my-hnr', $visitor);
//                return $this->redirect($redirect);
//            }
//
//        }

        $page = $params->page;
        $perPage = 10;

        $hnrSettings = [];
        $hnrSettings['hnr_method'] = $this->options()->xfdev_hintrun_check_method;
        $hnrSettings['min_seed_hours'] = $this->changeTimespentToReadableTime($this->options()->xfdev_hitnrun_minimum_seed_hours);
        $hnrSettings['min_ratio'] = $this->options()->xfdev_hitnrun_minimum_ratio;
        $hnrSettings['download_trigger'] = $this->options()->xfdev_hitnrun_download_trigger * 1048576;
        $hnrSettings['tolerance_period'] = $this->changeTimespentToReadableTime($this->options()->xfdev_hitnrun_check_tolerance_period);
        $hnrSettings['disable_leech_hnrs_enabled'] = $this->options()->xfdev_hitnrun_block_leech['xendevHitnRunBlockLeechEnabled'];
        $hnrSettings['disable_leech_hnrs'] = $this->options()->xfdev_hitnrun_block_leech['disableLeech_hnr'];
        $hnrSettings['disable_leech_hnrs_tolerance'] = $this->changeTimespentToReadableTime($this->options()->xfdev_hitnrun_block_leech['disableLeech_tolerance']);


        $viewParams = array(
            'options' => $hnr->limitByPage($page, $perPage)->fetch(),
            'count' => $hnr->total(),
            'hnrSettings' => $hnrSettings,
            'page' => $page,
            'perPage' => $perPage
        );

        return $this->view('', 'xfdev_member_my_hnr', $viewParams);
    }

    protected function changeTimespentToReadableTime($timespent)
    {
        switch ($timespent) {
            case $timespent < 24:
                $timespent = ($timespent) . ' hrs';
                break;
            default:
                $timespent = round(($timespent / 24), 2) . ' days';
                break;
        }
        return $timespent;
    }

    //Remove HNR and mark it as checked
    protected function zapHnr($userPeer, $leftAmount)
    {
        $userPeer->hit = 'no';
        $userPeer->hnr_checked = 3;
        $userPeer->uploaded += $leftAmount;
        $userPeer->seedtime = $this->options()->xfdev_hitnrun_minimum_seed_hours * 3600;
        $userPeer->hnr_last_checked = \XF::$time;
        $userPeer->save();
    }

    protected function getHitnrunRepo()
    {
        return \XF::finder('TorrentTracker:Peer')->with('Torrent', true)->where('hit', '=', 'yes');
    }

}