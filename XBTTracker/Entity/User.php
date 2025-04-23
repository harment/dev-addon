<?php

namespace Harment\XBTTracker\XF\Entity;

class User extends XFCP_User
{
    /**
     * Get the user's tracker statistics
     * الحصول على إحصائيات المتتبع للمستخدم
     *
     * @return \Harment\XBTTracker\Entity\UserStats|null
     */
    public function getTrackerStats()
    {
        return $this->em()->find('XBTTracker:UserStats', $this->user_id);
    }
    
    /**
     * Check if user has tracker stats
     * التحقق مما إذا كان المستخدم لديه إحصائيات متتبع
     *
     * @return bool
     */
    public function hasTrackerStats()
    {
        return (bool)$this->getTrackerStats();
    }
    
    /**
     * Create tracker stats for this user if they don't exist
     * إنشاء إحصائيات المتتبع لهذا المستخدم إذا لم تكن موجودة
     *
     * @return \Harment\XBTTracker\Entity\UserStats
     */
    public function getOrCreateTrackerStats()
    {
        $stats = $this->getTrackerStats();
        
        if (!$stats) {
            $stats = \Harment\XBTTracker\Entity\UserStats::createForUser($this->user_id);
        }
        
        return $stats;
    }
}