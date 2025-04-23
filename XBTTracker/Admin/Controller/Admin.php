<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;

class Admin extends AbstractController
{
    /**
     * Dashboard for BitTorrent tracker stats and management
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionIndex()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        // Get tracker stats
        $trackerService = $this->service('Harment\XBTTracker:Tracker');
        $stats = $trackerService->getStats();
        
        // Get latest torrents
        $latestTorrents = $this->getTorrentRepo()
            ->findTorrentsForList()
            ->order('creation_date', 'DESC')
            ->limit(10)
            ->fetch();
        
        // Get top users
        $topUsers = $this->getUserStatsRepo()
            ->findUserStatsForList()
            ->order('uploaded', 'DESC')
            ->limit(10)
            ->fetch();
        
        $viewParams = [
            'stats' => $stats,
            'latestTorrents' => $latestTorrents,
            'topUsers' => $topUsers,
            'trackerStatus' => [
                'connected' => $trackerService->isConnected(),
                'database' => $this->options()->harmentXbtTrackerDbName ?? 'xbt',
                'version' => 'XBT Tracker ' . $this->app()->addOnManager()->getById('Harment/XBTTracker')->version_string
            ],
            'announceUrl' => $this->options()->harmentXbtTrackerAnnounceUrl ?? $this->options()->xbtTrackerAnnounceURL
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Dashboard', 'harment_xbttracker_admin_dashboard', $viewParams);
    }
    
    /**
     * Get torrent repository
     *
     * @return \Harment\XBTTracker\Repository\Torrent
     */
    protected function getTorrentRepo()
    {
        return $this->repository('Harment\XBTTracker:Torrent');
    }
    
    /**
     * Get user stats repository
     *
     * @return \Harment\XBTTracker\Repository\UserStats
     */
    protected function getUserStatsRepo()
    {
        return $this->repository('Harment\XBTTracker:UserStats');
    }
}