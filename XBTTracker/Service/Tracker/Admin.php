<?php
// src/addons/Harment/XBTTracker/Service/Tracker/Admin.php
namespace Harment\XBTTracker\Service\Tracker;

class Admin extends Base
{
    /**
     * Get tracker stats
     *
     * @return array Stats including tracker status and various counts
     */
    public function getStats()
    {
        $stats = [
            'status' => $this->getTrackerStatus(),
            'torrents' => 0,
            'peers' => 0,
            'seeders' => 0,
            'leechers' => 0,
            'snatches' => 0
        ];
        
        try {
            $db = $this->db();
            
            // Get torrent count
            $stats['torrents'] = $db->fetchOne("
                SELECT COUNT(*)
                FROM xf_xbt_torrents
            ") ?: 0;
            
            // Get seeder/leecher count
            $peerStats = $db->fetchRow("
                SELECT 
                    COUNT(*) AS total,
                    SUM(IF(seeder = 1, 1, 0)) AS seeders,
                    SUM(IF(seeder = 0, 1, 0)) AS leechers
                FROM xf_xbt_peers
            ");
            
            if ($peerStats) {
                $stats['peers'] = (int)($peerStats['total'] ?: 0);
                $stats['seeders'] = (int)($peerStats['seeders'] ?: 0);
                $stats['leechers'] = (int)($peerStats['leechers'] ?: 0);
            }
            
            // Get snatch count
            $stats['snatches'] = (int)($db->fetchOne("
                SELECT SUM(completed)
                FROM xf_xbt_torrents
            ") ?: 0);
            
            return $stats;
        } catch (\Exception $e) {
            \XF::logException($e);
            return $stats;
        }
    }
    
    /**
     * Update all torrent stats from database
     *
     * @return bool Success or failure
     */
    public function updateAllTorrentStats()
    {
        try {
            $db = $this->db();
            
            // Update all torrent stats from peers table
            $db->query("
                UPDATE xf_xbt_torrents AS t
                LEFT JOIN (
                    SELECT 
                        torrent_id,
                        SUM(IF(seeder = 1, 1, 0)) AS seeders,
                        SUM(IF(seeder = 0, 1, 0)) AS leechers
                    FROM xf_xbt_peers
                    GROUP BY torrent_id
                ) AS p ON (t.torrent_id = p.torrent_id)
                SET 
                    t.seeders = IFNULL(p.seeders, 0),
                    t.leechers = IFNULL(p.leechers, 0)
            ");
            
            return true;
        } catch (\Exception $e) {
            \XF::logException($e);
            return false;
        }
    }
    
    /**
     * Check for hit and run torrents and warn users
     *
     * @return int Number of hit and run warnings sent
     */
    public function checkHitAndRun()
    {
        $hitAndRunHours = $this->options()->xbtTrackerHitAndRunHours;
        
        if ($hitAndRunHours <= 0) {
            return 0;
        }
        
        $cutoffTime = \XF::$time - ($hitAndRunHours * 3600);
        $db = $this->db();
        $warningCount = 0;
        
        try {
            // Find hit and run peers
            $hitAndRunPeers = $db->fetchAll("
                SELECT p.*, t.title
                FROM xf_xbt_peers AS p
                INNER JOIN xf_xbt_torrents AS t ON (p.torrent_id = t.torrent_id)
                WHERE p.completed = 1
                    AND p.seeder = 0
                    AND p.last_announce < ?
                    AND p.hit_and_run_warned = 0
            ", [$cutoffTime]);
            
            foreach ($hitAndRunPeers as $peer) {
                // Mark peer as warned
                $db->update('xf_xbt_peers', [
                    'hit_and_run_warned' => 1
                ], 'peer_id = ?', $peer['peer_id']);
                
                // Mark completed record
                $db->update('xf_xbt_user_completed', [
                    'hit_and_run' => 1
                ], 'user_id = ? AND torrent_id = ?', [$peer['user_id'], $peer['torrent_id']]);
                
                // Add warning to user stats
                /** @var \Harment\XBTTracker\Repository\UserStats $userStatsRepo */
                $userStatsRepo = \XF::repository('Harment\XBTTracker:UserStats');
                $userStatsRepo->addWarning($peer['user_id'], 'Hit and run on torrent: ' . $peer['title']);
                
                // Send warning message
                if ($this->sendHitAndRunWarning($peer['user_id'], $peer['title'])) {
                    $warningCount++;
                }
            }
            
            return $warningCount;
        } catch (\Exception $e) {
            \XF::logException($e);
            return 0;
        }
    }
    
    /**
     * Award bonus points for seeding
     *
     * @param int $pointsPerTorrent Points to award per torrent seeded
     * @return int Number of users awarded
     */
    public function awardBonusPoints($pointsPerTorrent = 5)
    {
        $db = $this->db();
        $userCount = 0;
        
        try {
            // Find active seeders
            $activeSeeds = $db->fetchAll("
                SELECT user_id, COUNT(*) AS seed_count
                FROM xf_xbt_peers
                WHERE seeder = 1
                GROUP BY user_id
            ");
            
            foreach ($activeSeeds as $seed) {
                $userId = $seed['user_id'];
                $seedCount = $seed['seed_count'];
                
                // Award points based on seed count
                $points = $seedCount * $pointsPerTorrent;
                
                if ($points > 0) {
                    /** @var \Harment\XBTTracker\Repository\UserStats $userStatsRepo */
                    $userStatsRepo = \XF::repository('Harment\XBTTracker:UserStats');
                    if ($userStatsRepo->awardBonusPoints($userId, $points, 'Bonus points for seeding ' . $seedCount . ' torrents')) {
                        $userCount++;
                    }
                }
            }
            
            return $userCount;
        } catch (\Exception $e) {
            \XF::logException($e);
            return 0;
        }
    }
    
    /**
     * Prune inactive peers
     * 
     * @param int $days Days of inactivity
     * @return int Number of peers removed
     */
    public function pruneInactivePeers($days = 1)
    {
        $cutoffTime = \XF::$time - ($days * 86400);
        $db = $this->db();
        $count = 0;
        
        try {
            // Find inactive peers
            $inactivePeers = $db->fetchAll("
                SELECT peer_id, user_id, torrent_id, seeder
                FROM xf_xbt_peers
                WHERE last_announce < ?
            ", [$cutoffTime]);
            
            foreach ($inactivePeers as $peer) {
                // Delete peer record
                $db->delete('xf_xbt_peers', 'peer_id = ?', $peer['peer_id']);
                $count++;
                
                // Update user stats
                $userStats = $this->finder('Harment\XBTTracker:UserStats')
                    ->where('user_id', $peer['user_id'])
                    ->fetchOne();
                    
                if ($userStats) {
                    if ($peer['seeder']) {
                        $userStats->active_seeds--;
                        if ($userStats->active_seeds < 0) {
                            $userStats->active_seeds = 0;
                        }
                    } else {
                        $userStats->active_leech--;
                        if ($userStats->active_leech < 0) {
                            $userStats->active_leech = 0;
                        }
                    }
                    
                    $userStats->save();
                }
                
                // Update torrent stats
                $torrent = $this->finder('Harment\XBTTracker:Torrent')
                    ->where('torrent_id', $peer['torrent_id'])
                    ->fetchOne();
                    
                if ($torrent) {
                    $this->updateTorrentStats($torrent);
                }
            }
            
            return $count;
        } catch (\Exception $e) {
            \XF::logException($e);
            return 0;
        }
    }
}