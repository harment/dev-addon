<?php
// src/addons/Harment/XBTTracker/Cron/Cleanup.php
namespace Harment\XBTTracker\Cron;

/**
 * Cron entry for performing routine cleanup tasks for the XBT tracker
 * يقوم بإجراء مهام التنظيف الدورية للتراكر
 */
class Cleanup
{
    /**
     * Performs routine cleanup tasks for the XBT tracker
     * This method is called automatically by XenForo's cron system
     *
     * @param int $d Days since last run (unused, kept for compatibility)
     * @param int $h Hours since last run (unused, kept for compatibility)
     * @param int $m Minutes since last run (unused, kept for compatibility)
     * @return bool
     */
    public static function cleanup($d = 0, $h = 0, $m = 0)
    {
        \XF::logDebug('XBT Tracker: Starting routine cleanup tasks');
        
        $startTime = microtime(true);
        $options = \XF::options();
        $cleanupResults = [];
        
        try {
            // تنظيف الأقران (peers) غير النشطين
            $peerInactiveDays = $options->harmentXbtTrackerPeerInactiveDays ?? 2;
            $peerCount = self::cleanupInactivePeers($peerInactiveDays);
            $cleanupResults['inactive_peers'] = $peerCount;
            
            // تنظيف التورنتات (torrents) المحذوفة
            $orphanedTorrentsCount = self::cleanupOrphanedTorrents();
            $cleanupResults['orphaned_torrents'] = $orphanedTorrentsCount;
            
            // تنظيف سجلات الإحصائيات القديمة
            $statsAgeDays = $options->harmentXbtTrackerStatsHistoryDays ?? 30;
            $statsCount = self::cleanupOldStatsHistory($statsAgeDays);
            $cleanupResults['old_stats'] = $statsCount;
            
            // تنظيف سجلات الأخطاء القديمة
            $errorLogAgeDays = $options->harmentXbtTrackerErrorLogDays ?? 7;
            $errorLogCount = self::cleanupErrorLogs($errorLogAgeDays);
            $cleanupResults['error_logs'] = $errorLogCount;
            
            // تنظيف سجلات الأحداث غير الضرورية
            $eventLogAgeDays = $options->harmentXbtTrackerEventLogDays ?? 14;
            $eventLogCount = self::cleanupEventLogs($eventLogAgeDays);
            $cleanupResults['event_logs'] = $eventLogCount;
            
            // تصحيح إحصائيات التورنت المتضاربة
            $torrentsFixedCount = self::fixInconsistentTorrentStats();
            $cleanupResults['fixed_torrents'] = $torrentsFixedCount;
            
            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);
            
            \XF::logDebug("XBT Tracker: Cleanup completed in {$executionTime} seconds. Results: " . 
                          json_encode($cleanupResults));
            
            return true;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'XBT Tracker cleanup error: ');
            return false;
        }
    }
    
    /**
     * Clean up inactive peers
     * 
     * @param int $days Number of days of inactivity before removal
     * @return int Number of peers removed
     */
    protected static function cleanupInactivePeers($days = 2)
    {
        $db = \XF::db();
        $cutoffTime = \XF::$time - ($days * 86400);
        
        try {
            // 1. تحديث حالة الأقران غير النشطين إلى غير نشط
            $db->update(
                'xf_harment_xbttracker_peer',
                ['active' => 0],
                'last_action < ? AND active = 1',
                [$cutoffTime]
            );
            
            // 2. حذف الأقران غير النشطين القدامى
            $result = $db->delete(
                'xf_harment_xbttracker_peer',
                'last_action < ? AND active = 0',
                [$cutoffTime]
            );
            
            // 3. تحديث إحصائيات التورنت
            $db->query("
                UPDATE xf_harment_xbttracker_torrent as t
                SET 
                    t.seeders = (
                        SELECT COUNT(*) FROM xf_harment_xbttracker_peer as p 
                        WHERE p.torrent_id = t.torrent_id AND p.seeder = 1 AND p.active = 1
                    ),
                    t.leechers = (
                        SELECT COUNT(*) FROM xf_harment_xbttracker_peer as p 
                        WHERE p.torrent_id = t.torrent_id AND p.seeder = 0 AND p.active = 1
                    )
            ");
            
            return $result;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'XBT Tracker inactive peers cleanup error: ');
            return 0;
        }
    }
    
    /**
     * Clean up orphaned torrents (torrents with no corresponding thread)
     * 
     * @return int Number of orphaned torrents removed
     */
    protected static function cleanupOrphanedTorrents()
    {
        $db = \XF::db();
        
        try {
            // 1. تحديد التورنتات التي ليس لها موضوع مقابل
            $orphanedTorrents = $db->fetchAllColumn("
                SELECT t.torrent_id
                FROM xf_harment_xbttracker_torrent AS t
                LEFT JOIN xf_thread AS thread ON t.thread_id = thread.thread_id
                WHERE thread.thread_id IS NULL AND t.thread_id > 0
            ");
            
            if (empty($orphanedTorrents)) {
                return 0;
            }
            
            // 2. حذف الأقران المرتبطة بهذه التورنتات
            $db->delete(
                'xf_harment_xbttracker_peer',
                'torrent_id IN (' . $db->quote($orphanedTorrents) . ')'
            );
            
            // 3. حذف التورنتات اليتيمة
            $result = $db->delete(
                'xf_harment_xbttracker_torrent',
                'torrent_id IN (' . $db->quote($orphanedTorrents) . ')'
            );
            
            return $result;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'XBT Tracker orphaned torrents cleanup error: ');
            return 0;
        }
    }
    
    /**
     * Clean up old statistics history
     * 
     * @param int $days Number of days before statistics history is removed
     * @return int Number of old statistics records removed
     */
    protected static function cleanupOldStatsHistory($days = 30)
    {
        $db = \XF::db();
        $cutoffTime = \XF::$time - ($days * 86400);
        $count = 0;
        
        try {
            // حذف سجلات إحصائيات قديمة من الجداول المختلفة
            $tables = [
                'xf_harment_xbttracker_stats_history',
                'xf_harment_xbttracker_user_history',
                'xf_harment_xbttracker_bonus_history'
            ];
            
            foreach ($tables as $table) {
                if ($db->tableExists($table)) {
                    $result = $db->delete(
                        $table,
                        'date < ?',
                        [$cutoffTime]
                    );
                    
                    $count += $result;
                }
            }
            
            return $count;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'XBT Tracker stats history cleanup error: ');
            return 0;
        }
    }
    
    /**
     * Clean up old error logs
     * 
     * @param int $days Number of days before error logs are removed
     * @return int Number of old error logs removed
     */
    protected static function cleanupErrorLogs($days = 7)
    {
        $db = \XF::db();
        $cutoffTime = \XF::$time - ($days * 86400);
        
        try {
            if ($db->tableExists('xf_harment_xbttracker_error_log')) {
                $result = $db->delete(
                    'xf_harment_xbttracker_error_log',
                    'log_date < ?',
                    [$cutoffTime]
                );
                
                return $result;
            }
            
            return 0;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'XBT Tracker error logs cleanup error: ');
            return 0;
        }
    }
    
    /**
     * Clean up old event logs
     * 
     * @param int $days Number of days before event logs are removed
     * @return int Number of old event logs removed
     */
    protected static function cleanupEventLogs($days = 14)
    {
        $db = \XF::db();
        $cutoffTime = \XF::$time - ($days * 86400);
        
        try {
            if ($db->tableExists('xf_harment_xbttracker_event_log')) {
                $result = $db->delete(
                    'xf_harment_xbttracker_event_log',
                    'log_date < ?',
                    [$cutoffTime]
                );
                
                return $result;
            }
            
            return 0;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'XBT Tracker event logs cleanup error: ');
            return 0;
        }
    }
    
    /**
     * Fix inconsistent torrent statistics
     * 
     * @return int Number of torrents with fixed statistics
     */
    protected static function fixInconsistentTorrentStats()
    {
        $db = \XF::db();
        $count = 0;
        
        try {
            // 1. تصحيح عدد البذور (seeders)
            $db->query("
                UPDATE xf_harment_xbttracker_torrent AS t
                SET t.seeders = (
                    SELECT COUNT(*) FROM xf_harment_xbttracker_peer AS p 
                    WHERE p.torrent_id = t.torrent_id AND p.seeder = 1 AND p.active = 1
                )
                WHERE t.seeders != (
                    SELECT COUNT(*) FROM xf_harment_xbttracker_peer AS p 
                    WHERE p.torrent_id = t.torrent_id AND p.seeder = 1 AND p.active = 1
                )
            ");
            
            $count += $db->getAffectedRows();
            
            // 2. تصحيح عدد الطالبين (leechers)
            $db->query("
                UPDATE xf_harment_xbttracker_torrent AS t
                SET t.leechers = (
                    SELECT COUNT(*) FROM xf_harment_xbttracker_peer AS p 
                    WHERE p.torrent_id = t.torrent_id AND p.seeder = 0 AND p.active = 1
                )
                WHERE t.leechers != (
                    SELECT COUNT(*) FROM xf_harment_xbttracker_peer AS p 
                    WHERE p.torrent_id = t.torrent_id AND p.seeder = 0 AND p.active = 1
                )
            ");
            
            $count += $db->getAffectedRows();
            
            // 3. تصحيح حجم الملف (file_size) إذا كان 0
            $db->query("
                UPDATE xf_harment_xbttracker_torrent
                SET file_size = 1024
                WHERE file_size = 0
            ");
            
            $count += $db->getAffectedRows();
            
            return $count;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'XBT Tracker inconsistent stats fix error: ');
            return 0;
        }
    }
}