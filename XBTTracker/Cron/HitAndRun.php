<?php
// src/addons/XBTTracker/Cron/HitAndRun.php
namespace XBTTracker\Cron;

/**
 * Cron entry for checking hit and run violations
 * يتحقق من المستخدمين الذين قاموا بتحميل التورنت ولم يقوموا ببذره لفترة كافية
 */
class HitAndRun
{
    /**
     * Check for hit and run violations
     *
     * @return bool
     */
    public static function checkHitAndRun()
    {
        // Only check if hit and run system is enabled
        $options = \XF::options();
        $hitAndRunHours = $options->xbtTrackerHitAndRunHours ?? 0;
        
        if ($hitAndRunHours <= 0) {
            return true; // النظام غير مفعل
        }
        
        try {
            // استخدام المستودع للتحقق من مخالفات Hit and Run
            /** @var \XBTTracker\Repository\UserStats $userStatsRepo */
            $userStatsRepo = \XF::repository('XBTTracker:UserStats');
            $warnedUsers = $userStatsRepo->checkHitAndRun();
            
            // استخدام خدمة التراكر كطريقة بديلة إذا كانت متاحة
            if (empty($warnedUsers) && \XF::app()->offsetExists('xbt.tracker.service')) {
                /** @var \XBTTracker\Service\Tracker $trackerService */
                $trackerService = \XF::app()->get('xbt.tracker.service');
                $trackerService->checkHitAndRun();
            }
            
            // Log action
            if (!empty($warnedUsers)) {
                \XF::logModeratorAction('xbt_tracker', 'hit_and_run', 'Warned ' . count($warnedUsers) . ' users for hit and run violations');
            }
            
            return true;
        } catch (\Exception $e) {
            \XF::logException($e);
            return false;
        }
    }
}