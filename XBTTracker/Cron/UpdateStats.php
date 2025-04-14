<?php
// src/addons/XBTTracker/Cron/UpdateStats.php
namespace XBTTracker\Cron;

/**
 * Cron entry for updating torrent and user statistics
 */
class UpdateStats
{
    /**
     * Update torrent and user statistics
     */
    public static function updateStats()
    {
        /** @var \XBTTracker\Repository\Torrent $torrentRepo */
        $torrentRepo = \XF::repository('XBTTracker:Torrent');
        $torrentRepo->updateAllTorrentStats();
        
        /** @var \XBTTracker\Repository\UserStats $userStatsRepo */
        $userStatsRepo = \XF::repository('XBTTracker:UserStats');
        $userStatsRepo->updateAllUserStats();
    }
}