<?php
// src/addons/XBTTracker/Service/Tracker/Base.php
namespace XBTTracker\Service\Tracker;

use XF\Service\AbstractService;

class Base extends AbstractService
{
    /**
     * TMDB API base URL
     */
    const TRACKER_INTERVAL = 60; // Announce interval in seconds
    
    /**
     * Get client IP address
     *
     * @return string
     */
    protected function getClientIp()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return $ip;
    }
    
    /**
     * Check if user can download the torrent
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @param \XBTTracker\Entity\UserStats $userStats
     * @return bool
     */
    protected function canDownload(\XBTTracker\Entity\Torrent $torrent, \XBTTracker\Entity\UserStats $userStats)
    {
        // Check if global freeleech is enabled
        if ($this->options()->xbtTrackerGlobalFreeleech) {
            return true;
        }
        
        // Check if torrent is freeleech
        if ($torrent->is_freeleech) {
            return true;
        }
        
        // Check user ratio
        $requiredRatio = $this->options()->xbtTrackerRequiredRatio;
        if ($requiredRatio > 0 && $userStats->ratio < $requiredRatio) {
            // Check if user is in an exempt group
            $user = $userStats->User;
            $exemptGroups = $this->options()->xbtTrackerRatioExemptGroups;
            
            if (!$exemptGroups || !$user || !array_intersect($user->secondary_group_ids, $exemptGroups)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Update user statistics
     *
     * @param \XBTTracker\Entity\UserStats $userStats
     * @param int $uploadDiff
     * @param int $downloadDiff
     * @param bool $isSeeder
     * @param int $seedChange
     */
    protected function updateUserStats(\XBTTracker\Entity\UserStats $userStats, $uploadDiff, $downloadDiff, $isSeeder, $seedChange = 0)
    {
        if ($uploadDiff > 0) {
            $userStats->uploaded += $uploadDiff;
        }
        
        if ($downloadDiff > 0) {
            $userStats->downloaded += $downloadDiff;
        }
        
        if ($isSeeder) {
            $userStats->active_seeds += $seedChange;
            if ($userStats->active_seeds < 0) {
                $userStats->active_seeds = 0;
            }
        } else {
            $userStats->active_leech += $seedChange;
            if ($userStats->active_leech < 0) {
                $userStats->active_leech = 0;
            }
        }
        
        $userStats->save();
    }
    
    /**
     * Record torrent completion by user
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @param int $userId
     */
    protected function recordCompletion(\XBTTracker\Entity\Torrent $torrent, $userId)
    {
        // Check if there's already a completion record
        $completion = $this->finder('XBTTracker:UserCompleted')
            ->where([
                'user_id' => $userId,
                'torrent_id' => $torrent->torrent_id
            ])
            ->fetchOne();
            
        if (!$completion) {
            $completion = $this->em()->create('XBTTracker:UserCompleted');
            $completion->user_id = $userId;
            $completion->torrent_id = $torrent->torrent_id;
            $completion->date = \XF::$time;
            $completion->save();
        }
    }
    
    /**
     * Update torrent statistics
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     */
    protected function updateTorrentStats(\XBTTracker\Entity\Torrent $torrent)
    {
        $db = $this->db();
        
        $stats = $db->fetchRow("
            SELECT
                SUM(IF(seeder = 1, 1, 0)) AS seeders,
                SUM(IF(seeder = 0, 1, 0)) AS leechers
            FROM xf_xbt_peers
            WHERE torrent_id = ?
        ", [$torrent->torrent_id]);
        
        $torrent->seeders = isset($stats['seeders']) ? $stats['seeders'] : 0;
        $torrent->leechers = isset($stats['leechers']) ? $stats['leechers'] : 0;
        $torrent->save();
    }
    
    /**
     * Check tracker status
     *
     * @return bool
     */
    public function getTrackerStatus()
    {
        $announceUrl = $this->options()->xbtTrackerAnnounceURL;
        
        if (!$announceUrl) {
            return false;
        }
        
        // Parse URL to get host and port
        $parsedUrl = parse_url($announceUrl);
        
        if (!isset($parsedUrl['host']) || !isset($parsedUrl['port'])) {
            return false;
        }
        
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'];
        
        // Try to connect to the tracker
        $socket = @fsockopen($host, $port, $errno, $errstr, 2);
        
        if (!$socket) {
            return false;
        }
        
        fclose($socket);
        return true;
    }
    
    /**
     * Send hit and run warning
     *
     * @param int $userId
     * @param string $torrentTitle
     * @return bool
     */
    protected function sendHitAndRunWarning($userId, $torrentTitle)
    {
        $user = \XF::em()->find('XF:User', $userId);
        if (!$user) {
            return false;
        }
        
        /** @var \XF\Mail\Mail $mail */
        $mail = \XF::app()->mailer()->newMail();
        
        $mail->setTemplate('xbt_hit_and_run_warning', [
            'user' => $user,
            'title' => $torrentTitle
        ]);
        
        $mail->setToUser($user);
        $mail->queue();
        
        return true;
    }
}