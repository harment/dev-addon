<?php
// src/addons/XBTTracker/Cron/BonusPoints.php
namespace XBTTracker\Cron;

/**
 * Cron entry for awarding bonus points
 * يمنح نقاط المكافآت للمستخدمين الذين يقومون بالبذر
 */
class BonusPoints
{
    /**
     * Award bonus points to active seeders
     *
     * @return bool
     */
    public static function awardBonusPoints()
    {
        // Get bonus points per hour from options
        $options = \XF::options();
        $pointsPerHour = $options->xbtTrackerBonusPointsPerHour ?? 1; // Default to 1 point per hour
        
        try {
            // استخدام المستودع لمنح النقاط
            /** @var \XBTTracker\Repository\UserStats $userStatsRepo */
            $userStatsRepo = \XF::repository('XBTTracker:UserStats');
            $awardedUsers = $userStatsRepo->awardBonusPoints($pointsPerHour);
            
            // استخدام خدمة التراكر كطريقة بديلة إذا كانت متاحة
            if (empty($awardedUsers) && \XF::app()->offsetExists('xbt.tracker.service')) {
                /** @var \XBTTracker\Service\Tracker $trackerService */
                $trackerService = \XF::app()->get('xbt.tracker.service');
                $trackerService->awardBonusPoints();
            }
            
            // Log action
            if (!empty($awardedUsers)) {
                \XF::logModeratorAction('xbt_tracker', 'bonus_points', 'Awarded bonus points to ' . count($awardedUsers) . ' users');
            }
            
            return true;
        } catch (\Exception $e) {
            \XF::logException($e);
            return false;
        }
    }
}