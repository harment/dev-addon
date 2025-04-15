<?php
// src/addons/XBTTracker/Repository/Torrent.php
namespace XBTTracker\Repository;

use XF\Mvc\Entity\Repository;

/**
 * مستودع التورنت
 * يوفر طرق للوصول إلى وإدارة التورنتات
 */
class Torrent extends Repository
{
    /**
     * الحصول على finder للتورنت
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function getTorrentFinder()
    {
        return $this->finder('XBTTracker:Torrent');
    }
    
    /**
     * العثور على التورنتات للقائمة
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findTorrentsForList()
    {
        return $this->getTorrentFinder()
            ->with(['User', 'Category'])
            ->setDefaultOrder('creation_date', 'desc');
    }
    
    /**
     * العثور على أحدث التورنتات
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\Finder
     */
    public function findLatestTorrents($limit = 20)
    {
        return $this->findTorrentsForList()
            ->limit($limit);
    }
    
    /**
     * العثور على التورنتات الأكثر تحميلًا
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\Finder
     */
    public function findMostDownloadedTorrents($limit = 10)
    {
        return $this->findTorrentsForList()
            ->order('completed', 'desc')
            ->limit($limit);
    }
    
    /**
     * العثور على التورنتات الأكثر بذرًا
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\Finder
     */
    public function findMostSeededTorrents($limit = 10)
    {
        return $this->findTorrentsForList()
            ->order('seeders', 'desc')
            ->limit($limit);
    }
    
    /**
     * العثور على التورنتات الأكثر مشاهدة
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\Finder
     */
    public function findMostViewedTorrents($limit = 10)
    {
        return $this->findTorrentsForList()
            ->order('view_count', 'desc')
            ->limit($limit);
    }
    
    /**
     * الحصول على إحصائيات التورنت المجمعة
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
     * حذف جميع الأقران للتورنت
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
     * تحديث إحصائيات التورنت
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
     * تحديث إحصائيات جميع التورنتات
     *
     * @return bool
     */
    public function updateAllTorrentStats()
    {
        $db = $this->db();
        
        // طريقة أكثر كفاءة من تحديث كل تورنت على حدة
        $db->query("
            UPDATE xf_xbt_torrents AS t
            LEFT JOIN (
                SELECT 
                    torrent_id,
                    SUM(IF(seeder = 1, 1, 0)) AS seeders,
                    SUM(IF(seeder = 0, 1, 0)) AS leechers
                FROM xf_xbt_peers
                GROUP BY torrent_id
            ) AS p ON (t.torrent_id = p.torrent_id)
            SET 
                t.seeders = IFNULL(p.seeders, 0),
                t.leechers = IFNULL(p.leechers, 0)
        ");
        
        return true;
    }
    
    /**
     * العثور على التورنتات بدون بذور (الميتة)
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findDeadTorrents()
    {
        return $this->getTorrentFinder()
            ->where('seeders', 0);
    }
    
    /**
     * العثور على التورنتات حسب الفئة
     *
     * @param int $categoryId
     * @return \XF\Mvc\Entity\Finder
     */
    public function findTorrentsByCategory($categoryId)
    {
        return $this->getTorrentFinder()
            ->where('category_id', $categoryId)
            ->setDefaultOrder('creation_date', 'desc');
    }
    
    /**
     * الحصول على ملفات التورنت
     *
     * @param string $infoHash
     * @return array
     */
    public function getTorrentFiles($infoHash)
    {
        $torrent = $this->getTorrentFinder()
            ->where('info_hash', $infoHash)
            ->fetchOne();
            
        if (!$torrent) {
            return [];
        }
        
        try {
            $filePath = $torrent->file_path;
            
            if (!file_exists($filePath) || !is_readable($filePath)) {
                return [];
            }
            
            $content = file_get_contents($filePath);
            if (!$content) {
                return [];
            }
            
            $bencode = new \XBTTracker\Util\Bencode();
            $torrentData = $bencode->decode($content);
            
            if (!$torrentData || !isset($torrentData['info'])) {
                return [];
            }
            
            $files = [];
            
            if (isset($torrentData['info']['files']) && is_array($torrentData['info']['files'])) {
                foreach ($torrentData['info']['files'] as $file) {
                    if (isset($file['path']) && is_array($file['path'])) {
                        $path = implode('/', $file['path']);
                        $size = $file['length'] ?? 0;
                        
                        $files[] = [
                            'path' => $path,
                            'size' => $size,
                            'size_formatted' => \XF::language()->fileSizeFormat($size)
                        ];
                    }
                }
            } else if (isset($torrentData['info']['name']) && isset($torrentData['info']['length'])) {
                $files[] = [
                    'path' => $torrentData['info']['name'],
                    'size' => $torrentData['info']['length'],
                    'size_formatted' => \XF::language()->fileSizeFormat($torrentData['info']['length'])
                ];
            }
            
            return $files;
        } catch (\Exception $e) {
            \XF::logException($e);
            return [];
        }
    }
    
    /**
     * التحقق مما إذا كان المستخدم قد شكر التورنت
     *
     * @param int $torrentId
     * @param int $userId
     * @return bool
     */
    public function hasThankedTorrent($torrentId, $userId)
    {
        $db = $this->db();
        
        $thanked = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_liked_content
            WHERE content_type = 'xbt_torrent' 
                AND content_id = ?
                AND user_id = ?
        ", [$torrentId, $userId]);
        
        return $thanked > 0;
    }
    
    /**
     * شكر تورنت
     *
     * @param int $torrentId
     * @param int $userId
     * @return bool
     */
    public function thankTorrent($torrentId, $userId)
    {
        // التحقق مما إذا كان تم شكر التورنت بالفعل
        if ($this->hasThankedTorrent($torrentId, $userId)) {
            return false;
        }
        
        $db = $this->db();
        
        // إدراج الإعجاب
        $db->insert('xf_liked_content', [
            'content_type' => 'xbt_torrent',
            'content_id' => $torrentId,
            'like_user_id' => $userId,
            'like_date' => \XF::$time
        ]);
        
        return true;
    }
    
    /**
     * زيادة عداد المشاهدات للتورنت
     *
     * @param int $torrentId
     * @return bool
     */
    public function incrementViewCount($torrentId)
    {
        $db = $this->db();
        
        $db->query("
            UPDATE xf_xbt_torrents
            SET view_count = view_count + 1
            WHERE torrent_id = ?
        ", $torrentId);
        
        return true;
    }
}