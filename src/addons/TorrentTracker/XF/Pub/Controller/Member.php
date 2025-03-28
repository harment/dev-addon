<?php

namespace TorrentTracker\XF\Pub\Controller;

use XF\Entity\User;
use XF\Mvc\ParameterBag;

class Member extends XFCP_Member
{

	public function actionTorrents(ParameterBag $params)
	{
		$user = $this->assertViewableUser($params->user_id);
		$visitor = \XF::visitor();
		$canViewOther = $visitor->hasPermission('xenTorrentTracker', 'viewMemberTorrentTabs');
		if ($user->user_id != $visitor->user_id && !$canViewOther)
		{
			return $this->noPermission();
		}

//		$page = $this->filterPage(); Instead of this using the below method to fetch /members/{$user_id}/page-2/torrents
		$page = $params->page;
		$perPage = 20;
		$filter = $this->filter('filter', 'str');
		$torrentRepo = $this->getTorrentRepo();
		if(!$filter)
		{
			$torrentFinder = $torrentRepo->findTorrentForList()
			->where('user_id', $user->user_id) 
			->limitByPage($page, $perPage);
		}
		else
		{
			$torrentFinder = $torrentRepo->findPeerTorrentForList()
			->where('user_id', $user->user_id);
			switch($filter)
			{
				case 'seed':
					$torrentFinder->where('active', 1)
						->where('left', 0);
					break;
				case 'leech':
					$torrentFinder->where('active', 1)
							->where('left','>', 0);  
					break;
				case 'inactive':
					$torrentFinder->where('active', 0); 
					break; 
				default:
					 
			} 
			$torrentFinder->limitByPage($page, $perPage);
		}


		$torrents = $torrentFinder->fetch();
		$torrentsCount = $torrentFinder->total(); 
		$viewParams = [
			'user' => $user,
			'torrents' => $torrents,
			'page' => $page,
			'filter' => $filter,
			'perPage' => $perPage,
			'total' => $torrentsCount, 
		];
		return $this->view('XF:Member\Torrents', 'member_torrents', $viewParams);
	}

	public function actionMyBonus(ParameterBag $params)
	{
		$visitor = \XF::visitor();
		if(!$visitor->user_id)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_user_not_found')));
		}

        $bonusPoints = $this->getTrackerRepo()->getAllBonusPoints();

        $bonusPointsValues = [];

        foreach ($bonusPoints as $key => $bonus)
        {
            $bonusPointsValues += [$bonus['points_needed']=>$key];
        }

        $options = $bonusPointsValues;

		$viewParams = array(
			'options' => $options
		); 
		return $this->view('XF:Member\MyBonus', 'xentorrent_member_mybonus', $viewParams);
	}

	public function actionMyBonusRedeem()
    {
        $points = $this->filter('points', 'int');

        $visitor = \XF::visitor();

        $bonusPoints = $this->getTrackerRepo()->getAllBonusPoints();

        $bonusPointsValues = [];

        foreach ($bonusPoints as $key => $bonus)
        {
            $bonusPointsValues += [$bonus['points_needed']=>$key];
        }

        $options = $bonusPointsValues;

        if ($this->isPost())
        {
            if (!in_array($points, array_keys($options)))
            {
                return $this->error(\XF::phrase('invalid_option_specified'));
            }
            elseif($points > $visitor['seedbonus'])
            {
                throw $this->exception($this->notFound(\XF::phrase('you_dont_have_enough_bonus_points_for_this_trade')));
            }

            //TODO: Fix the data being added to the user on using seedbonus
            $uploaded = $visitor['uploaded'] + ($options[$points] * 1073741824);
            $seedbonus = $visitor['seedbonus'] - $points;

            $visitor->uploaded = $uploaded;
            $visitor->seedbonus = $seedbonus;
            $visitor->save();

            return $this->redirect($this->buildLink('members/my-bonus', $visitor),\XF::phrase('seedbonus_redeemed_succesffully'));

        }else{
            $viewParams = [
                'points' => $points,
                'amount' => $options[$points]
            ];

            return $this->view('','xentorrent_bonus_points_redeem_confirmation',$viewParams);
        }
    }

	public function actionResetPassKey(ParameterBag $params)
	{
		$visitor = \XF::visitor();
		if(!$visitor->user_id)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_user_not_found')));
		}
		if ($this->isPost())
		{
			$torrent_pass_version = mt_rand();
			$visitor->torrent_pass_version = $torrent_pass_version;
			$visitor->save(); 
			return $this->message(\XF::phrase('your_changes_have_been_saved'));
		}
		$viewParams = array(
		); 
		return $this->view('XF:Member\ResetPassKey', 'xentorrent_reset_pass_key', $viewParams);
	}

	protected function getTorrentRepo()
	{
		return $this->repository('TorrentTracker:Torrent');
	}

	protected function getTrackerRepo()
    {
        return $this->repository('TorrentTracker:Tracker');
    }

    /**
     * When the user is permanently banned, than reset his passkey
     *
     * @param User $user
     * @return \XF\Mvc\FormAction
     * @throws \XF\PrintableException
     */
    protected function userBanSaveProcess(User $user)
    {
        $parent = parent::userBanSaveProcess($user);

        if($this->options()->xfttResetPasskeyOnBan)
        {
            $input = $this->filter([
                'ban_length' => 'str'
            ]);

            if ($input['ban_length'] == 'permanent')
            {
                $bannedUser = $user;
                $torrent_pass_version = mt_rand();
                $bannedUser->torrent_pass_version = $torrent_pass_version;
                $bannedUser->save();
            }
        }

        return $parent;
    }
}