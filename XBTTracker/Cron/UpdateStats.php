<?php
// src/addons/XBTTracker/Cron/UpdateStats.php
namespace XBTTracker\Cron;

/**
 * Cron entry for updating torrent and user statistics
 * يقوم بتحديث إحصائيات التورنت والمستخدمين بشكل دوري
 */
class UpdateStats
{
    /**
     * Update torrent and user statistics
     *
     * @return bool
     */
    public static function updateStats()
    {
        try {
            // الطريقة الأولى: استخدام المستودعات مباشرة
            $updated = 0;
            
            /** @var \XBTTracker\Repository\Torrent $torrentRepo */
            $torrentRepo = \XF::repository('XBTTracker:Torrent');
            if (method_exists($torrentRepo, 'updateAllTorrentStats')) {
                $torrentRepo->updateAllTorrentStats();
                $updated++;
            }
            
            /** @var \XBTTracker\Repository\UserStats $userStatsRepo */
            $userStatsRepo = \XF::repository('XBTTracker:UserStats');
            if (method_exists($userStatsRepo, 'updateAllUserStats')) {
                $userStatsRepo->updateAllUserStats();
                $updated++;
            }
            
            // الطريقة الثانية: استخدام خدمة التراكر إذا كانت الطريقة الأولى غير متاحة
            if ($updated < 2 && \XF::app()->offsetExists('xbt.tracker.service')) {
                /** @var \XBTTracker\Service\Tracker $trackerService */
                $trackerService = \XF::app()->get('xbt.tracker.service');
                
                if (method_exists($trackerService, 'updateTorrentStats')) {
                    $trackerService->updateTorrentStats();
                }
                
                if (method_exists($trackerService, 'updateUserStats')) {
                    $trackerService->updateUserStats();
                }
            }
            
            // تنظيف الأقران غير النشطين
            if (\XF::app()->offsetExists('xbt.tracker.service')) {
                /** @var \XBTTracker\Service\Tracker $trackerService */
                $trackerService = \XF::app()->get('xbt.tracker.service');
                
                if (method_exists($trackerService, 'pruneInactivePeers')) {
                    $inactiveDays = \XF::options()->xbtTrackerInactivePeersDays ?? 1;
                    $trackerService->pruneInactivePeers($inactiveDays);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            \XF::logException($e);
            return false;
        }
    }
}