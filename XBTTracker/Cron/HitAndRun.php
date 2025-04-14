<?php
// src/addons/XBTTracker/Cron/HitAndRun.php
namespace XBTTracker\Cron;

/**
 * Cron entry for checking hit and run violations
 */
class HitAndRun
{
    /**
     * Check for hit and run violations
     */
    public static function checkHitAndRun()
    {
        // Only check if hit and run system is enabled
        $hitAndRunHours = \XF::options()->xbtTrackerHitAndRunHours;
        
        if ($hitAndRunHours > 0) {
            /** @var \XBTTracker\Repository\UserStats $userStatsRepo */
            $userStatsRepo = \XF::repository('XBTTracker:UserStats');
            $warnedUsers = $userStatsRepo->checkHitAndRun();
            
            // Log action
            if (count($warnedUsers) > 0) {
                \XF::logModeratorAction('xbt_tracker', 'hit_and_run', 'Warned ' . count($warnedUsers) . ' users for hit and run violations');
            }
        }
    }
}