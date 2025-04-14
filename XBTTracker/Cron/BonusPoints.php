<?php
// src/addons/XBTTracker/Cron/BonusPoints.php
namespace XBTTracker\Cron;

/**
 * Cron entry for awarding bonus points
 */
class BonusPoints
{
    /**
     * Award bonus points to active seeders
     */
    public static function awardBonusPoints()
    {
        // Get bonus points per hour from options
        $pointsPerHour = 1; // Default to 1 point per hour
        
        /** @var \XBTTracker\Repository\UserStats $userStatsRepo */
        $userStatsRepo = \XF::repository('XBTTracker:UserStats');
        $awardedUsers = $userStatsRepo->awardBonusPoints($pointsPerHour);
        
        // Log action
        if (count($awardedUsers) > 0) {
            \XF::logModeratorAction('xbt_tracker', 'bonus_points', 'Awarded bonus points to ' . count($awardedUsers) . ' users');
        }
    }
}