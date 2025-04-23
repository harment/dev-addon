<?php
// src/addons/Harment/XBTTracker/Service/Torrent/Deleter.php
namespace Harment\XBTTracker\Service\Torrent;

use XF\Service\AbstractService;
use XF\Util\File;

class Deleter extends AbstractService
{
    /**
     * @var \Harment\XBTTracker\Entity\Torrent
     */
    protected $torrent;
    
    /**
     * Constructor
     * 
     * @param \XF\App $app
     * @param \Harment\XBTTracker\Entity\Torrent $torrent
     */
    public function __construct(\XF\App $app, \Harment\XBTTracker\Entity\Torrent $torrent)
    {
        parent::__construct($app);
        
        $this->torrent = $torrent;
    }
    
    /**
     * Delete torrent
     * 
     * @return bool Success or failure
     */
    public function delete()
    {
        $db = $this->db();
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Delete torrent file
            $this->deleteTorrentFile();
            
            // Delete poster file if exists
            $this->deletePosterFile();
            
            // Delete peers using repository
            $this->repository('Harment\XBTTracker:Torrent')->deletePeers($this->torrent->torrent_id);
            
            // Delete from completed table
            $db->delete('xf_xbt_user_completed', 'torrent_id = ?', $this->torrent->torrent_id);
            
            // Delete torrent entity
            $this->torrent->delete();
            
            // Commit transaction
            $db->commit();
            
            return true;
        } catch (\Exception $e) {
            // Rollback transaction
            $db->rollback();
            
            \XF::logException($e);
            return false;
        }
    }
    
    /**
     * Delete torrent file
     * 
     * @return bool Success or failure
     */
    protected function deleteTorrentFile()
    {
        $filePath = $this->torrent->file_path;
        
        if ($filePath && file_exists($filePath)) {
            return File::deleteFromAbstractedPath($filePath);
        }
        
        return true;
    }
    
    /**
     * Delete poster file
     * 
     * @return bool Success or failure
     */
    protected function deletePosterFile()
    {
        $posterPath = $this->torrent->poster_path;
        
        if ($posterPath && file_exists($posterPath)) {
            return File::deleteFromAbstractedPath($posterPath);
        }
        
        return true;
    }
    
    /**
     * Legacy method for backward compatibility
     * 
     * @param \Harment\XBTTracker\Entity\Torrent $torrent
     * @return bool Success or failure
     * @deprecated Use delete() instead
     */
    public function deleteTorrent(\Harment\XBTTracker\Entity\Torrent $torrent)
    {
        $this->torrent = $torrent;
        return $this->delete();
    }
}