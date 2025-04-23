<?php
// src/addons/Harment/XBTTracker/Service/Tracker.php
namespace Harment\XBTTracker\Service;

use XF\Service\AbstractService;

/**
 * Main tracker service class that provides access to tracker functionality
 * This class serves as an entry point and delegates to specialized services
 * 
 * @package Harment\XBTTracker\Service
 */
class Tracker extends AbstractService
{
    /**
     * @var Tracker\Announce
     */
    protected $announce;
    
    /**
     * @var Tracker\Admin
     */
    protected $admin;
    
    /**
     * Constructor
     * 
     * @param \XF\App $app Application instance
     */
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        
        $this->announce = $this->service('Harment\XBTTracker:Tracker\Announce');
        $this->admin = $this->service('Harment\XBTTracker:Tracker\Admin');
    }
    
    /**
     * Handle announce request from a BitTorrent client
     *
     * @param array $params Request parameters
     * @return array Response data for the tracker client
     */
    public function handleAnnounce(array $params)
    {
        try {
            return $this->announce->handleAnnounce($params);
        } catch (\Exception $e) {
            \XF::logException($e);
            return ['failure reason' => 'Internal tracker error'];
        }
    }
    
    /**
     * Get tracker stats (torrents, peers, seeders, leechers)
     *
     * @return array Stats with counts of various tracker metrics
     */
    public function getStats()
    {
        try {
            return $this->admin->getStats();
        } catch (\Exception $e) {
            \XF::logException($e);
            return [
                'status' => false,
                'torrents' => 0,
                'peers' => 0,
                'seeders' => 0,
                'leechers' => 0,
                'snatches' => 0
            ];
        }
    }
    
    /**
     * Check tracker status by attempting to connect to the tracker
     *
     * @return bool True if the tracker is online and responding
     */
    public function getTrackerStatus()
    {
        try {
            return $this->admin->getTrackerStatus();
        } catch (\Exception $e) {
            \XF::logException($e);
            return false;
        }
    }
    
    /**
     * Update statistics for all torrents from the peer information
     *
     * @return bool Success or failure
     */
    public function updateTorrentStats()
    {
        try {
            return $this->admin->updateAllTorrentStats();
        } catch (\Exception $e) {
            \XF::logException($e);
            return false;
        }
    }
    
    /**
     * Check for hit and run torrents and take appropriate action
     *
     * @return int Number of hit and run warnings sent
     */
    public function checkHitAndRun()
    {
        try {
            return $this->admin->checkHitAndRun();
        } catch (\Exception $e) {
            \XF::logException($e);
            return 0;
        }
    }
    
    /**
     * Award bonus points to users who are seeding
     *
     * @param int $pointsPerTorrent Points to award per torrent seeded
     * @return int Number of users awarded points
     */
    public function awardBonusPoints($pointsPerTorrent = 5)
    {
        try {
            return $this->admin->awardBonusPoints($pointsPerTorrent);
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
        if ($days < 1) {
            $days = 1;
        }
        
        try {
            return $this->admin->pruneInactivePeers($days);
        } catch (\Exception $e) {
            \XF::logException($e);
            return 0;
        }
    }
    
    /**
     * Generate a new passkey for a user
     *
     * @param int $userId User ID
     * @return string|false New passkey or false on failure
     */
    public function generateNewPasskey($userId)
    {
        try {
            $userStats = $this->finder('Harment\XBTTracker:UserStats')
                ->where('user_id', $userId)
                ->fetchOne();
                
            if (!$userStats) {
                return false;
            }
            
            // Generate new passkey
            $passkey = bin2hex(random_bytes(16));
            
            // Update user stats
            $userStats->passkey = $passkey;
            $userStats->save();
            
            return $passkey;
        } catch (\Exception $e) {
            \XF::logException($e);
            return false;
        }
    }
}