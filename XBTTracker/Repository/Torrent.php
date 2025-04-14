<?php
// src/addons/XBTTracker/Repository/Torrent.php
namespace XBTTracker\Repository;

use XF\Mvc\Entity\Repository;

class Torrent extends Repository
{
    /**
     * Find torrents for listing
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findTorrentsForList()
    {
        return $this->finder('XBTTracker:Torrent')
            ->with(['User', 'Category'])
            ->setDefaultOrder('creation_date', 'desc');
    }
    
    /**
     * Find latest torrents
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\Finder
     */
    public function findLatestTorrents($limit = 20)
    {
        return $this->finder('XBTTracker:Torrent')
            ->with(['User', 'Category'])
            ->order('creation_date', 'desc')
            ->limit($limit);
    }
    
    /**
     * Find most downloaded torrents
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\Finder
     */
    public function findMostDownloadedTorrents($limit = 10)
    {
        return $this->finder('XBTTracker:Torrent')
            ->with(['User', 'Category'])
            ->order('completed', 'desc')
            ->limit($limit);
    }
    
    /**
     * Find torrents with most seeders
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\Finder
     */
    public function findMostActiveSeedersTorrents($limit = 10)
    {
        return $this->finder('XBTTracker:Torrent')
            ->with(['User', 'Category'])
            ->order('seeders', 'desc')
            ->limit($limit);
    }
    
    /**
     * Find most viewed torrents
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\Finder
     */
    public function findMostViewedTorrents($limit = 10)
    {
        return $this->finder('XBTTracker:Torrent')
            ->with(['User', 'Category'])
            ->order('view_count', 'desc')
            ->limit($limit);
    }
    
    /**
     * Get combined torrent statistics
     *
     * @return array
     */
    public function getTorrentStats()
    {
        $db = $this->db();
        
        $stats = $db->fetchRow("
            SELECT 
                SUM(seeders) AS total_seeders,
                SUM(leechers) AS total_leechers,
                SUM(seeders) + SUM(leechers) AS total_peers,
                SUM(completed) AS total_snatches,
                COUNT(*) AS total_torrents
            FROM xf_xbt_torrents
        ");
        
        if (!$stats) {
            $stats = [
                'total_seeders' => 0,
                'total_leechers' => 0,
                'total_peers' => 0,
                'total_snatches' => 0,
                'total_torrents' => 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * Delete all peers for a torrent
     *
     * @param int $torrentId
     * @return bool
     */
    public function deletePeers($torrentId)
    {
        $db = $this->db();
        $db->delete('xf_xbt_peers', 'torrent_id = ?', $torrentId);
        
        return true;
    }
    
    /**
     * Update torrent statistics
     *
     * @param int $torrentId
     * @return bool
     */
    public function updateTorrentStats($torrentId)
    {
        $db = $this->db();
        
        $stats = $db->fetchRow("
            SELECT
                SUM(IF(seeder = 1, 1, 0)) AS seeders,
                SUM(IF(seeder = 0, 1, 0)) AS leechers
            FROM xf_xbt_peers
            WHERE torrent_id = ?
        ", [$torrentId]);
        
        if ($stats) {
            $db->update('xf_xbt_torrents', [
                'seeders' => $stats['seeders'] ?: 0,
                'leechers' => $stats['leechers'] ?: 0
            ], 'torrent_id = ?', $torrentId);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Update all torrent statistics
     *
     * @return bool
     */
    public function updateAllTorrentStats()
    {
        $db = $this->db();
        
        $torrents = $this->finder('XBTTracker:Torrent')->fetch();
        foreach ($torrents as $torrent) {
            $this->updateTorrentStats($torrent->torrent_id);
        }
        
        return true;
    }
    
    /**
     * Find torrents with no seeders (dead torrents)
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findDeadTorrents()
    {
        return $this->finder('XBTTracker:Torrent')
            ->where('seeders', 0);
    }
    
    /**
     * Find torrents by category
     *
     * @param int $categoryId
     * @return \XF\Mvc\Entity\Finder
     */
    public function findTorrentsByCategory($categoryId)
    {
        return $this->finder('XBTTracker:Torrent')
            ->where('category_id', $categoryId)
            ->setDefaultOrder('creation_date', 'desc');
    }
}