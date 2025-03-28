<?php

namespace TorrentTracker\Admin\Controller;

use TorrentTracker\Inc\Tracker;
use XF\Admin\Controller\AbstractController;

class Torrent extends AbstractController
{
	public function actionCheckStatus()
	{
		$result = Tracker::send(array(
			'action' => 'status'
		), false); 

		$status = 'offline'; 
		$stats = \XF::app()->simpleCache()->TorrentTracker->torrentTopStats;
		if (!is_array($stats))
		{
			$stats = array();
		} 
		if (isset($result['message']) && $result['message'] == 'online')
		{
			$status = 'online';	
			$stats['online'] = 1;		
		}
		$cache = \XF::app()->simpleCache()->TorrentTracker;
		$cache->torrentTopStats = $stats;
		$viewParams = array(
			'status' => $status
		);
		
		$view = $this->view('', 'xentorrent_check_status' , $viewParams); 
		return $view;
	}

	public function actionBonusPoints()
    {
        $this->setSectionContext('torrentBonusPoints');

        $viewParams = array(
            'bonusPoints' => $this->getTrackerRepo()->getAllBonusPoints()
        );

        return $this->view('','xentorrent_bonus_points_list',$viewParams);
    }

    public function actionBonusPointsAdd()
    {
        $this->setSectionContext('torrentBonusPoints');

        if ($this->isPost())
        {
            $input = $this->filter([
                'data_receivable' => 'int',
                'points_needed' => 'int',
                'display_order' => 'int'
            ]);

            try
            {
                $this->getTrackerRepo()->addBonusPoint($input['data_receivable'], $input['points_needed'], $input['display_order']);

                return $this->redirect($this->buildLink('torrents/bonus-points'));
            }
            catch (\Exception $e)
            {
                return $this->error($e->getMessage());
            }
        }

        $viewParams = array();
        return $this->view('', 'xentorrent_bonus_points_add', $viewParams);
    }

    public function actionBonusPointsDelete()
    {

        $input = $this->filter([
            'id' => 'int',
        ]);

        $this->getTrackerRepo()->removeBonusPoints($input['id']);

        return $this->redirect($this->buildLink('torrents/bonus-points'));
    }


	public function actionClientBan()
	{
        $this->setSectionContext('torrentsclientban');

        $viewParams = array(
			'clients' => $this->getTrackerRepo()->getAllBanClients()
		);

		return $this->view('', 'xentorrent_clientban_list', $viewParams);
	}

	public function actionClientBanAdd()
	{
        $this->setSectionContext('torrentsclientban');

        if ($this->isPost())
		{
			$input = $this->filter([
				'peer_id' => 'str',
				'comment' => 'str'
			]); 
			

			try 
			{
				$this->getTrackerRepo()->banClient($input['peer_id'], $input['comment']);

				return $this->redirect($this->buildLink('torrents/client-ban'));
			}
			catch (Exception $e)
			{
				return $this->responseError($e->getMessage());
			}
		}

		$viewParams = array();
		return $this->view('', 'xentorrent_clientban_add', $viewParams);
	}

	public function actionClientBanDelete()
	{
		$input = $this->filter([
				'peer_id' => 'str',
			]); 
		
		$this->getTrackerRepo()->removeBanClient($input['peer_id']);

		return $this->redirect($this->buildLink('torrents/client-ban'));
	}

	public function actionIpBan()
	{
	    $this->setSectionContext('torrentipban');

		$viewParams = array(
			'ips' => $this->getTrackerRepo()->getAllBanIps()
		);

		return $this->view('', 'xentorrent_ipban_list', $viewParams);
	}

	public function actionIpBanAdd()
	{
        $this->setSectionContext('torrentipban');

        if ($this->isPost())
		{
			$input = $this->filter([
				'begin' => 'str',
				'end' => 'str',
				'comment' => 'str' 
			]); 

			try 
			{
				$this->getTrackerRepo()->banIP($input['begin'], $input['end'], $input['comment']);

				return $this->redirect($this->buildLink('torrents/ip-ban'));
			}
			catch (\Exception $e)
			{
				return $this->error($e->getMessage());
			}
		}

		$viewParams = array();
		return $this->view('', 'xentorrent_ipban_add', $viewParams);
	}


    /**
     * @return \XF\Mvc\Reply\Redirect
     * Removes Banned Ip from Database
     */
    public function actionIpBanDelete()
	{
		$input = $this->filter([
				'begin' => 'str',
				'end' => 'str', 
			]);  

		$this->getTrackerRepo()->removeBanIp($input['begin'], $input['end']);

		return $this->redirect($this->buildLink('torrents/ip-ban'));
	}


    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
     * Displays Tracker Logs in AdminCP
     */
    public function actionLog()
	{
        $this->setSectionContext('torrentlogs');

		$logRepo = $this->getLogRepo();

		$id = $this->filter('log_id', 'int');
		if ($id)
		{
			$entry = $logRepo->getLogById($id);
			if (!$entry)
			{
				return $this->error( \XF::Phrase('requested_log_entry_not_found'));
			}

			$viewParams = array(
				'entry' => $logRepo->prepareLogEntry($entry)
			);

			return $this->view('', 'xentorrent_log_view', $viewParams);
		}
		else
		{
			$page = $this->filterPage();
			$perPage = 20;

			$entries = $logRepo->getLogEntries( $perPage,$page);

			$viewParams = array(
				'entries'	=>	$logRepo->prepareLogEntries($entries),
				'page'	=> $page,
				'total' => $logRepo->countLogEntries(),
				'perPage'	=> $perPage
			);

			return $this->view('', 'xentorrent_log', $viewParams);
		}
	}


    /**
     * @return \XF\Mvc\Entity\Repository
     * Returns Tracker Data from Database
     * Calls \TorrentTracker\Repository\Tracker
     */
    protected function getTrackerRepo()
	{
		return $this->repository('TorrentTracker:Tracker');
	}


    /**
     * @return \XF\Mvc\Entity\Repository
     * Returns Tracker Logs from Database
     * Calls \TorrentTracker\Repository\Log
     */
    protected function getLogRepo()
	{
		return $this->repository('TorrentTracker:Log');
	}
}