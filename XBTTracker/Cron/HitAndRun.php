<?php
// src/addons/Harment/XBTTracker/Cron/HitAndRun.php
namespace Harment\XBTTracker\Cron;

/**
 * Cron entry for checking hit and run violations
 * يتحقق من المستخدمين الذين قاموا بتحميل التورنت ولم يقوموا ببذره لفترة كافية
 */
class HitAndRun
{
    /**
     * Check for hit and run violations
     * This method is called automatically by XenForo's cron system
     *
     * @param int $d Days since last run (unused, kept for compatibility)
     * @param int $h Hours since last run (unused, kept for compatibility)
     * @param int $m Minutes since last run (unused, kept for compatibility)
     * @return bool
     */
    public static function checkHitAndRun($d = 0, $h = 0, $m = 0)
    {
        // Only check if hit and run system is enabled
        $options = \XF::options();
        $hitAndRunHours = $options->harmentXbtTrackerHitAndRunHours ?? 0;
        
        if ($hitAndRunHours <= 0) {
            \XF::logDebug('XBT Tracker: Hit and run system is disabled');
            return true; // النظام غير مفعل
        }
        
        $minRatio = $options->harmentXbtTrackerHitAndRunMinRatio ?? 1.0;
        $warningsToAdd = $options->harmentXbtTrackerHitAndRunWarnings ?? 1;
        
        \XF::logDebug('XBT Tracker: Starting hit and run checks');
        
        try {
            // استخدام المستودع للتحقق من مخالفات Hit and Run
            /** @var \Harment\XBTTracker\Repository\UserStats $userStatsRepo */
            $userStatsRepo = \XF::repository('Harment\XBTTracker:UserStats');
            $warnedUsers = $userStatsRepo->checkHitAndRun($hitAndRunHours, $minRatio, $warningsToAdd);
            
            // استخدام خدمة التراكر كطريقة بديلة إذا كانت متاحة
            if (empty($warnedUsers) && \XF::app()->offsetExists('harment.xbttracker.service')) {
                /** @var \Harment\XBTTracker\Service\Tracker $trackerService */
                $trackerService = \XF::app()->get('harment.xbttracker.service');
                $warnedUsers = $trackerService->checkHitAndRun($hitAndRunHours, $minRatio, $warningsToAdd);
            }
            
            // Log action
            if (!empty($warnedUsers)) {
                \XF::logModeratorAction(
                    'harment_xbttracker', 
                    'hit_and_run', 
                    'Warned ' . count($warnedUsers) . ' users for hit and run violations'
                );
                
                \XF::logDebug('XBT Tracker: Added warnings to ' . count($warnedUsers) . ' users for hit and run violations');
                
                // Record warning history
                self::recordHitAndRunWarnings($warnedUsers);
            } else {
                \XF::logDebug('XBT Tracker: No hit and run violations detected');
            }
            
            return true;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'XBT Tracker hit and run check error: ');
            return false;
        }
    }
    
    /**
     * Records hit and run warnings to the database
     * 
     * @param array $warnedUsers Array of [user_id => torrent_counts]
     * @return void
     */
    protected static function recordHitAndRunWarnings(array $warnedUsers)
    {
        if (empty($warnedUsers)) {
            return;
        }
        
        $db = \XF::db();
        $time = \XF::$time;
        
        try {
            $db->beginTransaction();
            
            foreach ($warnedUsers as $userId => $torrentCount) {
                $db->insert('xf_xbt_user_warnings', [
                    'user_id' => $userId,
                    'warning_count' => $torrentCount,
                    'reason' => 'Automatic warning for hit and run violation',
                    'date' => $time
                ], false, 'warning_count = warning_count + VALUES(warning_count)');
            }
            
            $db->commit();
        } catch (\Exception $e) {
            $db->rollback();
            \XF::logException($e, false, 'XBT Tracker hit and run warnings record error: ');
        }
    }
}