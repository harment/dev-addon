<?php
// src/addons/Harment/XBTTracker/Cron/UpdateStats.php
namespace Harment\XBTTracker\Cron;

/**
 * Cron entry for updating torrent and user statistics
 * يقوم بتحديث إحصائيات التورنت والمستخدمين بشكل دوري
 */
class UpdateStats
{
    /**
     * Update torrent and user statistics
     * This method is called automatically by XenForo's cron system
     *
     * @param int $d Days since last run (unused, kept for compatibility)
     * @param int $h Hours since last run (unused, kept for compatibility)
     * @param int $m Minutes since last run (unused, kept for compatibility)
     * @return bool
     */
    public static function updateStats($d = 0, $h = 0, $m = 0)
    {
        \XF::logDebug('XBT Tracker: Starting statistics update');
        
        $startTime = microtime(true);
        $options = \XF::options();
        
        try {
            // الطريقة الأولى: استخدام المستودعات مباشرة
            $updated = 0;
            
            // تحديث إحصائيات التورنت
            /** @var \Harment\XBTTracker\Repository\Torrent $torrentRepo */
            $torrentRepo = \XF::repository('Harment\XBTTracker:Torrent');
            if (method_exists($torrentRepo, 'updateAllTorrentStats')) {
                $count = $torrentRepo->updateAllTorrentStats();
                $updated++;
                \XF::logDebug("XBT Tracker: Updated statistics for {$count} torrents");
            }
            
            // تحديث إحصائيات المستخدمين
            /** @var \Harment\XBTTracker\Repository\UserStats $userStatsRepo */
            $userStatsRepo = \XF::repository('Harment\XBTTracker:UserStats');
            if (method_exists($userStatsRepo, 'updateAllUserStats')) {
                $count = $userStatsRepo->updateAllUserStats();
                $updated++;
                \XF::logDebug("XBT Tracker: Updated statistics for {$count} users");
            }
            
            // الطريقة الثانية: استخدام خدمة التراكر إذا كانت الطريقة الأولى غير متاحة
            if ($updated < 2 && \XF::app()->offsetExists('harment.xbttracker.service')) {
                /** @var \Harment\XBTTracker\Service\Tracker $trackerService */
                $trackerService = \XF::app()->get('harment.xbttracker.service');
                
                if (method_exists($trackerService, 'updateTorrentStats')) {
                    $count = $trackerService->updateTorrentStats();
                    \XF::logDebug("XBT Tracker: Updated torrent statistics using tracker service ({$count} torrents)");
                }
                
                if (method_exists($trackerService, 'updateUserStats')) {
                    $count = $trackerService->updateUserStats();
                    \XF::logDebug("XBT Tracker: Updated user statistics using tracker service ({$count} users)");
                }
            }
            
            // حساب إحصائيات عامة وتخزينها في الذاكرة المؤقتة
            if (method_exists($torrentRepo, 'calculateGlobalStats')) {
                $globalStats = $torrentRepo->calculateGlobalStats();
                \XF::registry()->set('harmentXbtTrackerStats', $globalStats);
                \XF::logDebug("XBT Tracker: Updated global statistics cache");
            }
            
            // تنظيف الأقران غير النشطين
            if (\XF::app()->offsetExists('harment.xbttracker.service')) {
                /** @var \Harment\XBTTracker\Service\Tracker $trackerService */
                $trackerService = \XF::app()->get('harment.xbttracker.service');
                
                if (method_exists($trackerService, 'pruneInactivePeers')) {
                    $inactiveDays = $options->harmentXbtTrackerInactivePeersDays ?? 1;
                    $count = $trackerService->pruneInactivePeers($inactiveDays);
                    \XF::logDebug("XBT Tracker: Pruned {$count} inactive peers (older than {$inactiveDays} days)");
                }
            }
            
            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);
            \XF::logDebug("XBT Tracker: Statistics update completed in {$executionTime} seconds");
            
            return true;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'XBT Tracker stats update error: ');
            return false;
        }
    }
    
    /**
     * Rebuild torrent stats cache - called manually when needed
     * 
     * @return bool
     */
    public static function rebuildStatsCache()
    {
        try {
            /** @var \Harment\XBTTracker\Repository\Torrent $torrentRepo */
            $torrentRepo = \XF::repository('Harment\XBTTracker:Torrent');
            
            if (method_exists($torrentRepo, 'calculateGlobalStats')) {
                $globalStats = $torrentRepo->calculateGlobalStats();
                \XF::registry()->set('harmentXbtTrackerStats', $globalStats);
                
                \XF::logDebug("XBT Tracker: Manually rebuilt global statistics cache");
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'XBT Tracker stats cache rebuild error: ');
            return false;
        }
    }
}