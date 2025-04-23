<?php
// src/addons/Harment/XBTTracker/Service/Tracker/Base.php
namespace Harment\XBTTracker\Service\Tracker;

use XF\Service\AbstractService;
use Harment\XBTTracker\Entity\Torrent;
use Harment\XBTTracker\Entity\UserStats;

class Base extends AbstractService
{
    /**
     * Default tracker announce interval in seconds
     */
    const TRACKER_INTERVAL = 60;
    
    /**
     * Get client IP address with support for proxies
     *
     * @return string Client IP address
     */
    protected function getClientIp()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        return $ip;
    }
    
    /**
     * Check if user can download the torrent based on ratio requirements
     *
     * @param Torrent $torrent The torrent entity
     * @param UserStats $userStats The user stats entity
     * @return bool True if user can download, false otherwise
     */
    protected function canDownload(Torrent $torrent, UserStats $userStats)
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
     * Update user statistics with upload and download data
     *
     * @param UserStats $userStats The user stats entity
     * @param int $uploadDiff Upload bytes difference
     * @param int $downloadDiff Download bytes difference
     * @param bool $isSeeder Whether user is seeding
     * @param int $seedChange Change in seed count (1 for new seed, -1 for removed seed)
     */
    protected function updateUserStats(UserStats $userStats, $uploadDiff, $downloadDiff, $isSeeder, $seedChange = 0)
    {
        // Only add positive upload values
        if ($uploadDiff > 0) {
            $userStats->uploaded += $uploadDiff;
        }
        
        // Only add positive download values
        if ($downloadDiff > 0) {
            $userStats->downloaded += $downloadDiff;
        }
        
        // Update active seeds/leeches
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
     * @param Torrent $torrent The torrent entity
     * @param int $userId User ID
     * @return bool Success or failure
     */
    protected function recordCompletion(Torrent $torrent, $userId)
    {
        // Check if there's already a completion record
        $completion = $this->finder('Harment\XBTTracker:UserCompleted')
            ->where([
                'user_id' => $userId,
                'torrent_id' => $torrent->torrent_id
            ])
            ->fetchOne();
            
        if (!$completion) {
            try {
                $completion = $this->em()->create('Harment\XBTTracker:UserCompleted');
                $completion->user_id = $userId;
                $completion->torrent_id = $torrent->torrent_id;
                $completion->date = \XF::$time;
                $completion->save();
                
                return true;
            } catch (\Exception $e) {
                \XF::logException($e);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Update torrent seeder and leecher statistics
     *
     * @param Torrent $torrent The torrent entity
     * @return bool Success or failure
     */
    protected function updateTorrentStats(Torrent $torrent)
    {
        try {
            $db = $this->db();
            
            $stats = $db->fetchRow("
                SELECT
                    SUM(IF(seeder = 1, 1, 0)) AS seeders,
                    SUM(IF(seeder = 0, 1, 0)) AS leechers
                FROM xf_xbt_peers
                WHERE torrent_id = ?
            ", [$torrent->torrent_id]);
            
            $torrent->seeders = isset($stats['seeders']) ? (int)$stats['seeders'] : 0;
            $torrent->leechers = isset($stats['leechers']) ? (int)$stats['leechers'] : 0;
            $torrent->save();
            
            return true;
        } catch (\Exception $e) {
            \XF::logException($e);
            return false;
        }
    }
    
    /**
     * Check tracker status by attempting to connect to the tracker
     *
     * @return bool True if tracker is online, false otherwise
     */
    public function getTrackerStatus()
    {
        $announceUrl = $this->options()->xbtTrackerAnnounceURL;
        
        if (!$announceUrl) {
            return false;
        }
        
        // Parse URL to get host and port
        $parsedUrl = parse_url($announceUrl);
        
        if (!isset($parsedUrl['host'])) {
            return false;
        }
        
        $host = $parsedUrl['host'];
        $port = isset($parsedUrl['port']) ? $parsedUrl['port'] : 80;
        
        // Try to connect to the tracker
        $socket = @fsockopen($host, $port, $errno, $errstr, 2);
        
        if (!$socket) {
            return false;
        }
        
        fclose($socket);
        return true;
    }
    
    /**
     * Send hit and run warning email to user
     *
     * @param int $userId User ID
     * @param string $torrentTitle Torrent title
     * @return bool Success or failure
     */
    protected function sendHitAndRunWarning($userId, $torrentTitle)
    {
        $user = \XF::em()->find('XF:User', $userId);
        if (!$user) {
            return false;
        }
        
        try {
            /** @var \XF\Mail\Mail $mail */
            $mail = \XF::app()->mailer()->newMail();
            
            $mail->setTemplate('xbt_hit_and_run_warning', [
                'user' => $user,
                'title' => $torrentTitle
            ]);
            
            $mail->setToUser($user);
            $mail->queue();
            
            return true;
        } catch (\Exception $e) {
            \XF::logException($e);
            return false;
        }
    }
}