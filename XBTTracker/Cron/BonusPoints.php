<?php
// src/addons/Harment/XBTTracker/Cron/BonusPoints.php
namespace Harment\XBTTracker\Cron;

/**
 * Cron entry for awarding bonus points
 * يمنح نقاط المكافآت للمستخدمين الذين يقومون بالبذر
 */
class BonusPoints
{
    /**
     * Award bonus points to active seeders
     * This method is called automatically by XenForo's cron system
     * 
     * @param int $d Days since last run (unused, kept for compatibility)
     * @param int $h Hours since last run (unused, kept for compatibility)
     * @param int $m Minutes since last run (unused, kept for compatibility)
     * @return bool
     */
    public static function awardBonusPoints($d = 0, $h = 0, $m = 0)
    {
        // تأكد من تفعيل نظام المكافآت
        $options = \XF::options();
        $enabled = $options->harmentXbtTrackerEnableBonusPoints ?? true;
        
        if (!$enabled) {
            \XF::logDebug('XBT Tracker: Bonus points system is disabled');
            return true;
        }
        
        // Get bonus points per hour from options
        $pointsPerHour = $options->harmentXbtTrackerBonusPerHour ?? 1; // Default to 1 point per hour
        $multiplier = $options->harmentXbtTrackerBonusMultiplierForSeeding ?? 2; // Default multiplier for seeding
        
        \XF::logDebug('XBT Tracker: Starting bonus points award process');
        
        try {
            // استخدام المستودع لمنح النقاط
            /** @var \Harment\XBTTracker\Repository\UserStats $userStatsRepo */
            $userStatsRepo = \XF::repository('Harment\XBTTracker:UserStats');
            $awardedUsers = $userStatsRepo->awardBonusPoints($pointsPerHour, $multiplier);
            
            // استخدام خدمة التراكر كطريقة بديلة إذا كانت متاحة
            if (empty($awardedUsers) && \XF::app()->offsetExists('harment.xbttracker.service')) {
                /** @var \Harment\XBTTracker\Service\Tracker $trackerService */
                $trackerService = \XF::app()->get('harment.xbttracker.service');
                $awardedUsers = $trackerService->awardBonusPoints($pointsPerHour, $multiplier);
            }
            
            // Log action
            if (!empty($awardedUsers)) {
                \XF::logModeratorAction(
                    'harment_xbttracker', 
                    'bonus_points', 
                    'Awarded bonus points to ' . count($awardedUsers) . ' users'
                );
                
                \XF::logDebug('XBT Tracker: Awarded bonus points to ' . count($awardedUsers) . ' users');
                
                // Record history in the database if applicable
                self::recordBonusPointsHistory($awardedUsers);
            } else {
                \XF::logDebug('XBT Tracker: No users qualified for bonus points at this time');
            }
            
            return true;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'XBT Tracker bonus points error: ');
            return false;
        }
    }
    
    /**
     * Records bonus points history to the database
     * 
     * @param array $awardedUsers Array of [user_id => points_awarded]
     * @return void
     */
    protected static function recordBonusPointsHistory(array $awardedUsers)
    {
        if (empty($awardedUsers)) {
            return;
        }
        
        $db = \XF::db();
        $time = \XF::$time;
        
        try {
            $db->beginTransaction();
            
            foreach ($awardedUsers as $userId => $points) {
                $db->insert('xf_xbt_user_bonus_history', [
                    'user_id' => $userId,
                    'points' => $points,
                    'reason' => 'Automatic bonus for seeding',
                    'date' => $time
                ]);
            }
            
            $db->commit();
        } catch (\Exception $e) {
            $db->rollback();
            \XF::logException($e, false, 'XBT Tracker bonus history error: ');
        }
    }
}