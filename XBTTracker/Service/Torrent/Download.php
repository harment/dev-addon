<?php

namespace Harment\XBTTracker\Service\Torrent;

use XF\Service\AbstractService;

class Download extends AbstractService
{
    /**
     * @var \Harment\XBTTracker\Entity\Torrent
     */
    protected $torrent;
    
    /**
     * @var \Harment\XBTTracker\Entity\UserStats
     */
    protected $userStats;
    
    /**
     * Constructor
     * 
     * @param \XF\App $app
     * @param \Harment\XBTTracker\Entity\Torrent $torrent
     * @param \Harment\XBTTracker\Entity\UserStats $userStats
     */
    public function __construct(\XF\App $app, \Harment\XBTTracker\Entity\Torrent $torrent, \Harment\XBTTracker\Entity\UserStats $userStats)
    {
        parent::__construct($app);
        
        $this->torrent = $torrent;
        $this->userStats = $userStats;
    }
    
    /**
     * Get torrent file for download
     * 
     * @return string|false Torrent file content if successful, false otherwise
     */
    public function getTorrentFile()
    {
        $filePath = $this->torrent->file_path;
        
        if (!$filePath || !file_exists($filePath)) {
            return false;
        }
        
        $torrentContent = file_get_contents($filePath);
        if (!$torrentContent) {
            return false;
        }
        
        // Load the bencode library
        require_once(\XF::getSourceDirectory() . '/addons/Harment/XBTTracker/bencode.php');
        
        try {
            // Decode the torrent file
            $decoded = \Bencode::decode($torrentContent);
            
            // Add or update tracker information
            $announceUrl = $this->getAnnounceUrl();
            
            if (!empty($announceUrl)) {
                $decoded['announce'] = $announceUrl;
                
                // Remove announce-list if exists
                if (isset($decoded['announce-list'])) {
                    unset($decoded['announce-list']);
                }
            }
            
            // Re-encode the torrent
            $updatedContent = \Bencode::encode($decoded);
            
            return $updatedContent;
        } catch (\Exception $e) {
            \XF::logException($e);
            return false;
        }
    }
    
    /**
     * Get announce URL with passkey
     * 
     * @return string Announce URL with passkey
     */
    protected function getAnnounceUrl()
    {
        $announceUrl = $this->options()->xbtTrackerAnnounceURL;
        $passkey = $this->userStats->passkey;
        
        if (!$announceUrl || !$passkey) {
            return '';
        }
        
        // Append passkey to announce URL
        if (strpos($announceUrl, '?') !== false) {
            $announceUrl .= '&passkey=' . $passkey;
        } else {
            $announceUrl .= '?passkey=' . $passkey;
        }
        
        return $announceUrl;
    }
}