<?php
// src/addons/XBTTracker/Repository/UserStats.php
namespace XBTTracker\Repository;

use XF\Mvc\Entity\Repository;

class UserStats extends Repository
{
    /**
     * Update user statistics based on peer data
     *
     * @param int $userId
     * @return bool
     */
    public function updateUserStats($userId)
    {
        $db = $this->db();
        
        $stats = $db->fetchRow("
            SELECT
                SUM(IF(seeder = 1, 1, 0)) AS active_seeds,
                SUM(IF(seeder = 0, 1, 0)) AS active_leech
            FROM xf_xbt_peers
            WHERE user_id = ?
        ", [$userId]);
        
        if ($stats) {
            $db->update('xf_xbt_user_stats', [
                'active_seeds' => $stats['active_seeds'] ?: 0,
                'active_leech' => $stats['active_leech']
				'active_leech' => $stats['active_leech'] ?: 0
            ], 'user_id = ?', $userId);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Update all user statistics
     *
     * @return bool
     */
    public function updateAllUserStats()
    {
        $db = $this->db();
        
        $userStats = $this->finder('XBTTracker:UserStats')->fetch();
        foreach ($userStats as $stats) {
            $this->updateUserStats($stats->user_id);
        }
        
        return true;
    }
    
    /**
     * Check for hit and run violations
     *
     * @return array List of users warned
     */
    public function checkHitAndRun()
    {
        $db = $this->db();
        $hitAndRunHours = \XF::options()->xbtTrackerHitAndRunHours;
        
        if (!$hitAndRunHours) {
            return [];
        }
        
        // Get completed torrents where the user has stopped seeding before the minimum time
        $minSeedTime = \XF::$time - ($hitAndRunHours * 3600);
        
        $completedTorrents = $this->finder('XBTTracker:UserCompleted')
            ->with(['User', 'Torrent'])
            ->where('hit_and_run', 0)
            ->where('date', '<', $minSeedTime)
            ->where('seeded_until', 0)
            ->fetch();
            
        $warnedUsers = [];
        
        foreach ($completedTorrents as $completed) {
            // Check if user is still seeding
            $isSeeding = $this->isUserSeeding($completed->user_id, $completed->torrent_id);
            
            if (!$isSeeding) {
                // User has hit and run
                $completed->hit_and_run = 1;
                $completed->save();
                
                // Increment user warnings
                $userStats = $this->getUserStats($completed->user_id);
                if ($userStats) {
                    $userStats->warnings++;
                    $userStats->save();
                    
                    // Send warning to user
                    $this->sendHitAndRunWarning($completed->user_id, $completed->Torrent);
                    
                    $warnedUsers[] = $completed->user_id;
                }
            } else {
                // User is seeding, update the seeded_until time
                $completed->seeded_until = \XF::$time;
                $completed->save();
            }
        }
        
        return $warnedUsers;
    }
    
    /**
     * Check if user is seeding a torrent
     *
     * @param int $userId
     * @param int $torrentId
     * @return bool
     */
    public function isUserSeeding($userId, $torrentId)
    {
        $peer = $this->finder('XBTTracker:Peer')
            ->where([
                'user_id' => $userId,
                'torrent_id' => $torrentId,
                'seeder' => 1
            ])
            ->fetchOne();
            
        return ($peer !== null);
    }
    
    /**
     * Award bonus points to seeders
     *
     * @param int $pointsPerHour Points to award per hour of seeding
     * @return array List of users awarded points
     */
    public function awardBonusPoints($pointsPerHour = 1)
    {
        if ($pointsPerHour <= 0) {
            return [];
        }
        
        $db = $this->db();
        
        // Get active seeders
        $peers = $this->finder('XBTTracker:Peer')
            ->where('seeder', 1)
            ->fetch();
            
        $awardedUsers = [];
        
        foreach ($peers as $peer) {
            $userId = $peer->user_id;
            $lastAnnounce = $peer->last_announce;
            
            // Skip if last announce is more than 1 hour ago
            if ((\XF::$time - $lastAnnounce) > 3600) {
                continue;
            }
            
            // Award bonus points
            $userStats = $this->getUserStats($userId);
            if ($userStats) {
                $userStats->bonus_points += $pointsPerHour;
                $userStats->save();
                
                // Record bonus history
                $this->recordBonusHistory($userId, $pointsPerHour, 'Seeding reward');
                
                $awardedUsers[$userId] = ($awardedUsers[$userId] ?? 0) + $pointsPerHour;
            }
        }
        
        return $awardedUsers;
    }
    
    /**
     * Record bonus point history
     *
     * @param int $userId
     * @param int $points
     * @param string $reason
     * @return bool
     */
    public function recordBonusHistory($userId, $points, $reason)
    {
        $bonusHistory = $this->em()->create('XBTTracker:BonusHistory');
        $bonusHistory->user_id = $userId;
        $bonusHistory->points = $points;
        $bonusHistory->reason = $reason;
        $bonusHistory->date = \XF::$time;
        $bonusHistory->save();
        
        return true;
    }
    
    /**
     * Get user stats
     *
     * @param int $userId
     * @return \XBTTracker\Entity\UserStats|null
     */
    public function getUserStats($userId)
    {
        $userStats = $this->finder('XBTTracker:UserStats')
            ->where('user_id', $userId)
            ->fetchOne();
            
        if (!$userStats) {
            $userStats = $this->em()->create('XBTTracker:UserStats');
            $userStats->user_id = $userId;
            $userStats->save();
        }
        
        return $userStats;
    }
    
    /**
     * Send hit and run warning to user
     *
     * @param int $userId
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool
     */
    protected function sendHitAndRunWarning($userId, \XBTTracker\Entity\Torrent $torrent)
    {
        /** @var \XF\Repository\User $userRepo */
        $userRepo = $this->repository('XF:User');
        $user = $userRepo->getUserById($userId);
        
        if (!$user) {
            return false;
        }
        
        /** @var \XF\Service\User\TempChange $notifier */
        $notifier = $this->service('XF:User\TempChange', $user);
        
        $notifier->setNotification('xbt_hit_and_run_warning', [
            'title' => $torrent->title,
            'link' => \XF::app()->router('public')->buildLink('torrents', $torrent)
        ]);
        
        $notifier->notify();
        
        return true;
    }
}
