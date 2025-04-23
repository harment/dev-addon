<?php

namespace Harment\XBTTracker\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Torrent extends Repository
{
    /**
     * البحث عن التورنتات للعرض في القائمة
     *
     * @return Finder
     */
    public function findTorrentsForList()
    {
        return $this->finder('Harment\XBTTracker:Torrent')
            ->setDefaultOrder('creation_date', 'desc');
    }
    
    /**
     * البحث عن تورنت بواسطة info_hash
     *
     * @param string $infoHash
     * @return Finder
     */
    public function findTorrentByInfoHash($infoHash)
    {
        return $this->finder('Harment\XBTTracker:Torrent')
            ->where('info_hash', $infoHash);
    }
    
    /**
     * البحث عن التورنتات الأكثر شعبية
     *
     * @param int $limit عدد التورنتات المطلوبة
     * @return Finder
     */
    public function findPopularTorrents($limit = 10)
    {
        return $this->finder('Harment\XBTTracker:Torrent')
            ->order('seeders', 'desc')
            ->where('seeders', '>', 0)
            ->limit($limit);
    }
    
    /**
     * البحث عن أحدث التورنتات
     *
     * @param int $limit عدد التورنتات المطلوبة
     * @return Finder
     */
    public function findLatestTorrents($limit = 10)
    {
        return $this->finder('Harment\XBTTracker:Torrent')
            ->order('creation_date', 'desc')
            ->limit($limit);
    }
    
    /**
     * البحث عن التورنتات بواسطة الفئة
     *
     * @param int $categoryId معرف الفئة
     * @return Finder
     */
    public function findTorrentsByCategory($categoryId)
    {
        return $this->finder('Harment\XBTTracker:Torrent')
            ->where('category_id', $categoryId)
            ->setDefaultOrder('creation_date', 'desc');
    }
    
    /**
     * البحث عن التورنتات بواسطة المستخدم
     *
     * @param int $userId معرف المستخدم
     * @return Finder
     */
    public function findTorrentsByUser($userId)
    {
        return $this->finder('Harment\XBTTracker:Torrent')
            ->where('user_id', $userId)
            ->setDefaultOrder('creation_date', 'desc');
    }
    
    /**
     * البحث عن التورنتات بواسطة النص
     *
     * @param string $searchTerm نص البحث
     * @return Finder
     */
    public function findTorrentsBySearchTerm($searchTerm)
    {
        return $this->finder('Harment\XBTTracker:Torrent')
            ->where('title', 'like', '%' . $searchTerm . '%')
            ->setDefaultOrder('creation_date', 'desc');
    }
    
    /**
     * الحصول على إحصائيات التراكر
     *
     * @return array
     */
    public function getTrackerStats()
    {
        $db = $this->db();
        
        $stats = [
            'total_torrents' => 0,
            'total_seeders' => 0,
            'total_leechers' => 0,
            'total_peers' => 0,
            'downloaded_today' => 0,
            'uploaded_today' => 0,
            'users_with_passkeys' => 0,
            'last_updated' => time()
        ];
        
        // عدد التورنتات
        $stats['total_torrents'] = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_xbt_torrents
        ") ?: 0;
        
        // مجموع السيدرز واللتشرز
        $peerStats = $db->fetchRow("
            SELECT 
                SUM(CASE WHEN seeder = 1 THEN 1 ELSE 0 END) AS seeders,
                SUM(CASE WHEN seeder = 0 THEN 1 ELSE 0 END) AS leechers
            FROM xf_xbt_peers
        ");
        
        if ($peerStats) {
            $stats['total_seeders'] = $peerStats['seeders'] ?: 0;
            $stats['total_leechers'] = $peerStats['leechers'] ?: 0;
            $stats['total_peers'] = $stats['total_seeders'] + $stats['total_leechers'];
        }
        
        // عدد المستخدمين الذين لديهم passkeys
        $stats['users_with_passkeys'] = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_user
            WHERE xbt_passkey IS NOT NULL AND xbt_passkey != ''
        ") ?: 0;
        
        // إحصائيات التحميل والرفع اليومية (تقريبية)
        $todayStart = strtotime('today');
        $transferStats = $db->fetchRow("
            SELECT 
                SUM(downloaded) AS downloaded,
                SUM(uploaded) AS uploaded
            FROM xf_xbt_peers
            WHERE last_announce >= ?
        ", $todayStart);
        
        if ($transferStats) {
            $stats['downloaded_today'] = $transferStats['downloaded'] ?: 0;
            $stats['uploaded_today'] = $transferStats['uploaded'] ?: 0;
        }
        
        return $stats;
    }
    
    /**
     * الحصول على إحصائيات التورنت
     *
     * @param int $torrentId معرف التورنت
     * @return array
     */
    public function getTorrentStats($torrentId)
    {
        $db = $this->db();
        
        $stats = [
            'seeders' => 0,
            'leechers' => 0,
            'completed' => 0
        ];
        
        // عدد السيدرز واللتشرز
        $peerStats = $db->fetchRow("
            SELECT 
                SUM(CASE WHEN seeder = 1 THEN 1 ELSE 0 END) AS seeders,
                SUM(CASE WHEN seeder = 0 THEN 1 ELSE 0 END) AS leechers
            FROM xf_xbt_peers
            WHERE torrent_id = ?
        ", $torrentId);
        
        if ($peerStats) {
            $stats['seeders'] = $peerStats['seeders'] ?: 0;
            $stats['leechers'] = $peerStats['leechers'] ?: 0;
        }
        
        // عدد التحميلات المكتملة
        $stats['completed'] = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_xbt_user_completed
            WHERE torrent_id = ?
        ", $torrentId) ?: 0;
        
        return $stats;
    }
    
    /**
     * تحديث إحصائيات التورنت
     *
     * @param int $torrentId معرف التورنت
     * @return void
     */
    public function updateTorrentStats($torrentId)
    {
        $stats = $this->getTorrentStats($torrentId);
        
        $torrent = $this->em->find('Harment\XBTTracker:Torrent', $torrentId);
        if ($torrent) {
            $torrent->seeders = $stats['seeders'];
            $torrent->leechers = $stats['leechers'];
            $torrent->completed = $stats['completed'];
            $torrent->save();
        }
    }
}