<?php
// src/addons/XBTTracker/Service/Torrent/Deleter.php
namespace Harment\XBTTracker\Service\Torrent;

use XF\Service\AbstractService;
use XF\Util\File;

class Deleter extends AbstractService
{
    /**
     * @var \XBTTracker\Entity\Torrent
     */
    protected $torrent;
    
    /**
     * Constructor
     */
    public function __construct(\XF\App $app, \XBTTracker\Entity\Torrent $torrent)
    {
        parent::__construct($app);
        
        $this->torrent = $torrent;
    }
    
    /**
     * Delete torrent
     * 
     * @return bool
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
            $this->repository('XBTTracker:Torrent')->deletePeers($this->torrent->torrent_id);
            
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
     */
    protected function deleteTorrentFile()
    {
        $filePath = $this->torrent->file_path;
        
        if ($filePath && file_exists($filePath)) {
            File::deleteFromAbstractedPath($filePath);
        }
    }
    
    /**
     * Delete poster file
     */
    protected function deletePosterFile()
    {
        $posterPath = $this->torrent->poster_path;
        
        if ($posterPath && file_exists($posterPath)) {
            File::deleteFromAbstractedPath($posterPath);
        }
    }
    
    /**
     * Legacy method for backward compatibility
     * 
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool
     */
    public function deleteTorrent(\XBTTracker\Entity\Torrent $torrent)
    {
        $this->torrent = $torrent;
        return $this->delete();
    }
}