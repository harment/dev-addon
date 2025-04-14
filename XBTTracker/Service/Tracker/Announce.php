<?php
// src/addons/XBTTracker/Service/Tracker/Announce.php
namespace XBTTracker\Service\Tracker;

use XF\Service\AbstractService;

class Announce extends AbstractService
{
    /**
     * Handle announce request from a BitTorrent client
     *
     * @param array $params Request parameters
     * @return array Response data
     */
    public function handleAnnounce(array $params)
    {
        // Required parameters
        $infoHash = isset($params['info_hash']) ? bin2hex($params['info_hash']) : null;
        $peerId = isset($params['peer_id']) ? $params['peer_id'] : null;
        $port = isset($params['port']) ? intval($params['port']) : 0;
        $uploaded = isset($params['uploaded']) ? intval($params['uploaded']) : 0;
        $downloaded = isset($params['downloaded']) ? intval($params['downloaded']) : 0;
        $left = isset($params['left']) ? intval($params['left']) : 0;
        $event = isset($params['event']) ? $params['event'] : '';
        $passkey = isset($params['passkey']) ? $params['passkey'] : null;
        
        // Optional parameters
        $numWant = isset($params['numwant']) ? intval($params['numwant']) : 50;
        $compact = isset($params['compact']) ? intval($params['compact']) : 0;
        
        // IP address
        $ip = $this->getClientIp();
        
        // Validate required parameters
        if (!$infoHash || !$peerId || !$port || !$passkey) {
            return $this->error('Invalid request parameters');
        }
        
        // Validate passkey and get user
        $userStats = $this->finder('XBTTracker:UserStats')
            ->where('passkey', $passkey)
            ->fetchOne();
            
        if (!$userStats) {
            return $this->error('Invalid passkey');
        }
        
        $userId = $userStats->user_id;
        
        // Get torrent by info hash
        $torrent = $this->finder('XBTTracker:Torrent')
            ->where('info_hash', $infoHash)
            ->fetchOne();
            
        if (!$torrent) {
            return $this->error('Torrent not found');
        }
        
        // Check if user can download this torrent
        if (!$this->canDownload($torrent, $userStats)) {
            return $this->error('You are not allowed to download this torrent');
        }
        
        // Determine if this is a seeder or leecher
        $isSeeder = ($left == 0);
        
        // Find existing peer record
        $peer = $this->finder('XBTTracker:Peer')
            ->where([
                'torrent_id' => $torrent->torrent_id,
                'user_id' => $userId,
                'peer_id_binary' => $peerId
            ])
            ->fetchOne();
            
        // Update statistics based on event
        switch ($event) {
            case 'started':
                // New peer
                if (!$peer) {
                    $peer = $this->em()->create('XBTTracker:Peer');
                    $peer->torrent_id = $torrent->torrent_id;
                    $peer->user_id = $userId;
                    $peer->peer_id_binary = $peerId;
                    $peer->first_announce = \XF::$time;
                }
                
                $peer->ip = $ip;
                $peer->port = $port;
                $peer->uploaded = $uploaded;
                $peer->downloaded = $downloaded;
                $peer->left_bytes = $left;
                $peer->seeder = $isSeeder;
                $peer->last_announce = \XF::$time;
                $peer->passkey = $passkey;
                $peer->save();
                
                // Update user statistics
                $this->updateUserStats($userStats, $uploaded, $downloaded, $isSeeder);
                
                break;
                
            case 'stopped':
                // Remove peer
                if ($peer) {
                    // Update user statistics before deleting
                    $this->updateUserStats(
                        $userStats,
                        $uploaded - $peer->uploaded,
                        $downloaded - $peer->downloaded,
                        $isSeeder,
                        -1
                    );
                    
                    $peer->delete();
                }
                
                break;
                
            case 'completed':
                // User finished downloading the torrent
                if ($peer) {
                    $peer->ip = $ip;
                    $peer->port = $port;
                    $peer->uploaded = $uploaded;
                    $peer->downloaded = $downloaded;
                    $peer->left_bytes = $left;
                    $peer->seeder = $isSeeder;
                    $peer->last_announce = \XF::$time;
                    $peer->completed = true;
                    $peer->save();
                    
                    // Update user statistics
                    $this->updateUserStats(
                        $userStats,
                        $uploaded - $peer->uploaded,
                        $downloaded - $peer->downloaded,
                        $isSeeder
                    );
                    
                    // Record completion
                    $this->recordCompletion($torrent, $userId);
                    
                    // Increment torrent completed count
                    $torrent->completed++;
                    $torrent->save();
                }
                
                break;
                
            default:
                // Regular update
                if ($peer) {
                    $peer->ip = $ip;
                    $peer->port = $port;
                    $peer->uploaded = $uploaded;
                    $peer->downloaded = $downloaded;
                    $peer->left_bytes = $left;
                    $peer->seeder = $isSeeder;
                    $peer->last_announce = \XF::$time;
                    $peer->save();
                    
                    // Update user statistics
                    $this->updateUserStats(
                        $userStats,
                        $uploaded - $peer->uploaded,
                        $downloaded - $peer->downloaded,
                        $isSeeder
                    );
                }
                else {
                    // New peer (no specific event)
                    $peer = $this->em()->create('XBTTracker:Peer');
                    $peer->torrent_id = $torrent->torrent_id;
                    $peer->user_id = $userId;
                    $peer->peer_id_binary = $peerId;
                    $peer->ip = $ip;
                    $peer->port = $port;
                    $peer->uploaded = $uploaded;
                    $peer->downloaded = $downloaded;
                    $peer->left_bytes = $left;
                    $peer->seeder = $isSeeder;
                    $peer->first_announce = \XF::$time;
                    $peer->last_announce = \XF::$time;
                    $peer->passkey = $passkey;
                    $peer->save();
                    
                    // Update user statistics
                    $this->updateUserStats($userStats, $uploaded, $downloaded, $isSeeder);
                }
        }
        
        // Update torrent statistics
        $this->updateTorrentStats($torrent);
        
        // Get peers for response
        $peers = $this->getPeersForResponse($torrent, $userId, $numWant, $compact);
        
        // Prepare response
        $response = [
            'interval' => 60,  // Announce interval in seconds
            'min interval' => 60,
            'complete' => $torrent->seeders,
            'incomplete' => $torrent->leechers,
            'peers' => $peers
        ];
        
        return $response;
    }
    
    /**
     * Generate error response
     *
     * @param string $message Error message
     * @return array Error response
     */
    protected function error($message)
    {
        return [
            'failure reason' => $message
        ];
    }
    
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
        if ($this->app->options()->xbtTrackerGlobalFreeleech) {
            return true;
        }
        
        // Check if torrent is freeleech
        if ($torrent->is_freeleech) {
            return true;
        }
        
        // Check user ratio
        $requiredRatio = $this->app->options()->xbtTrackerRequiredRatio;
        if ($requiredRatio > 0 && $userStats->ratio < $requiredRatio) {
            // Check if user is in an exempt group
            $user = $userStats->User;
            $exemptGroups = $this->app->options()->xbtTrackerRatioExemptGroups;
            
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
     * Get peers for response
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @param int $userId
     * @param int $numWant
     * @param bool $compact
     * @return array|string
     */
    protected function getPeersForResponse(\XBTTracker\Entity\Torrent $torrent, $userId, $numWant, $compact)
    {
        $peers = $this->finder('XBTTracker:Peer')
            ->where('torrent_id', $torrent->torrent_id)
            ->where('user_id', '!=', $userId)
            ->order('RAND()')
            ->limit($numWant)
            ->fetch();
            
        if ($compact) {
            $compactPeers = '';
            foreach ($peers as $peer) {
                $ip = $peer->ip;
                $port = $peer->port;
                
                $ipParts = explode('.', $ip);
                if (count($ipParts) == 4) {
                    $compactPeers .= chr($ipParts[0]) . chr($ipParts[1]) . chr($ipParts[2]) . chr($ipParts[3]) . pack('n', $port);
                }
            }
            
            return $compactPeers;
        } else {
            $peerList = [];
            foreach ($peers as $peer) {
                $peerList[] = [
                    'peer id' => bin2hex($peer->peer_id_binary),
                    'ip' => $peer->ip,
                    'port' => $peer->port
                ];
            }
            
            return $peerList;
        }
    }
}