<?php
// src/addons/XBTTracker/Service/Torrent/Deleter.php
namespace XBTTracker\Service\Torrent;

use XF\Service\AbstractService;

class Deleter extends AbstractService
{
    /**
     * Delete a torrent
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool
     */
    public function deleteTorrent(\XBTTracker\Entity\Torrent $torrent)
    {
        // Delete the torrent file
        if ($torrent->file_path && file_exists($torrent->file_path)) {
            @unlink($torrent->file_path);
        }
        
        // Delete the poster file
        if ($torrent->poster_path && file_exists($torrent->poster_path)) {
            @unlink($torrent->poster_path);
        }
        
        // Delete related peer records
        $this->repository('XBTTracker:Torrent')->deletePeers($torrent->torrent_id);
        
        // Delete the torrent entity
        $torrent->delete();
        
        return true;
    }
}
