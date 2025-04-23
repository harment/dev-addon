<?php
// src/addons/Harment/XBTTracker/Service/Tracker/Announce.php
namespace Harment\XBTTracker\Service\Tracker;

use Harment\XBTTracker\Entity\Torrent;

class Announce extends Base
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
        $userStats = $this->finder('Harment\XBTTracker:UserStats')
            ->where('passkey', $passkey)
            ->fetchOne();
            
        if (!$userStats) {
            return $this->error('Invalid passkey');
        }
        
        $userId = $userStats->user_id;
        
        // Get torrent by info hash
        $torrent = $this->finder('Harment\XBTTracker:Torrent')
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
        $peer = $this->finder('Harment\XBTTracker:Peer')
            ->where([
                'torrent_id' => $torrent->torrent_id,
                'user_id' => $userId,
                'peer_id_binary' => $peerId
            ])
            ->fetchOne();
        
        try {
            // Update statistics based on event
            switch ($event) {
                case 'started':
                    // New peer
                    if (!$peer) {
                        $peer = $this->em()->create('Harment\XBTTracker:Peer');
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
                    $this->updateUserStats($userStats, $uploaded, $downloaded, $isSeeder, 1);
                    
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
                        $prevSeeder = $peer->seeder;
                        
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
                            $isSeeder,
                            ($prevSeeder != $isSeeder) ? ($isSeeder ? 1 : -1) : 0
                        );
                    }
                    else {
                        // New peer (no specific event)
                        $peer = $this->em()->create('Harment\XBTTracker:Peer');
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
                        $this->updateUserStats($userStats, $uploaded, $downloaded, $isSeeder, 1);
                    }
            }
            
            // Update torrent statistics
            $this->updateTorrentStats($torrent);
            
            // Get peers for response
            $peers = $this->getPeersForResponse($torrent, $userId, $numWant, $compact);
            
            // Prepare response
            $response = [
                'interval' => self::TRACKER_INTERVAL,
                'min interval' => self::TRACKER_INTERVAL,
                'complete' => $torrent->seeders,
                'incomplete' => $torrent->leechers,
                'peers' => $peers
            ];
            
            return $response;
        } catch (\Exception $e) {
            \XF::logException($e);
            return $this->error('Internal tracker error');
        }
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
     * Get peers for response
     *
     * @param Torrent $torrent The torrent entity
     * @param int $userId User ID
     * @param int $numWant Maximum number of peers to return
     * @param bool $compact Whether to return compact peer list
     * @return array|string Peer list
     */
    protected function getPeersForResponse(Torrent $torrent, $userId, $numWant, $compact)
    {
        $peers = $this->finder('Harment\XBTTracker:Peer')
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