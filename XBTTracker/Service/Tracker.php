<?php
// src/addons/XBTTracker/Service/Tracker.php
namespace Harment\XBTTracker\Service;

use XF\Service\AbstractService;

/**
 * Main tracker service class that provides access to tracker functionality
 * This class serves as an entry point and delegates to specialized services
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
     */
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        
        $this->announce = $this->service('XBTTracker:Tracker\Announce');
        $this->admin = $this->service('XBTTracker:Tracker\Admin');
    }
    
    /**
     * Handle announce request from a BitTorrent client
     *
     * @param array $params Request parameters
     * @return array Response data
     */
    public function handleAnnounce(array $params)
    {
        return $this->announce->handleAnnounce($params);
    }
    
    /**
     * Get tracker stats (torrents, peers, seeders, leechers)
     *
     * @return array
     */
    public function getStats()
    {
        return $this->admin->getStats();
    }
    
    /**
     * Check tracker status
     *
     * @return bool
     */
    public function getTrackerStatus()
    {
        return $this->admin->getTrackerStatus();
    }
    
    /**
     * Update statistics for all torrents
     *
     * @return bool
     */
    public function updateTorrentStats()
    {
        return $this->admin->updateAllTorrentStats();
    }
    
    /**
     * Check for hit and run torrents and take appropriate action
     *
     * @return bool
     */
    public function checkHitAndRun()
    {
        return $this->admin->checkHitAndRun();
    }
    
    /**
     * Award bonus points to users who are seeding
     *
     * @return bool
     */
    public function awardBonusPoints()
    {
        return $this->admin->awardBonusPoints();
    }
    
    /**
     * Prune inactive peers
     *
     * @param int $days Days of inactivity
     * @return int Number of peers removed
     */
    public function pruneInactivePeers($days = 1)
    {
        return $this->admin->pruneInactivePeers($days);
    }