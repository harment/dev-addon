<?php

namespace XBTTracker\Service\Torrent;

use XF\Service\AbstractService;

class Download extends AbstractService
{
    /**
     * @var \XBTTracker\Entity\Torrent
     */
    protected $torrent;
    
    /**
     * @var \XBTTracker\Entity\UserStats
     */
    protected $userStats;
    
    /**
     * Constructor
     */
    public function __construct(\XF\App $app, \XBTTracker\Entity\Torrent $torrent, \XBTTracker\Entity\UserStats $userStats)
    {
        parent::__construct($app);
        
        $this->torrent = $torrent;
        $this->userStats = $userStats;
    }
    
    /**
     * Get torrent file for download
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
        require_once(\XF::getSourceDirectory() . '/addons/XBTTracker/bencode.php');
        
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
            return false;
        }
    }
    
    /**
     * Get announce URL with passkey
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