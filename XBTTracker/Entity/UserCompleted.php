<?php
// src/addons/XBTTracker/Entity/UserCompleted.php
namespace Harment\XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * User Completed Entity
 * Stores information about torrents that a user has completed
 * 
 * كيان اكتمال المستخدم
 * يخزن معلومات عن التورنتات التي أكملها المستخدم
 *
 * @property int $completed_id
 * @property int $user_id
 * @property int $torrent_id
 * @property int $date
 * @property int $seeded_until
 * @property bool $hit_and_run
 *
 * @property-read \XF\Entity\User $User
 * @property-read \Harment\XBTTracker\Entity\Torrent $Torrent
 */
class UserCompleted extends Entity
{
    /**
     * Define the entity structure
     * تعريف هيكل الكيان
     *
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xbt_user_completed';
        $structure->shortName = 'XBTTracker:UserCompleted';
        $structure->primaryKey = 'completed_id';
        
        $structure->columns = [
            'completed_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'torrent_id' => ['type' => self::UINT, 'required' => true],
            'date' => ['type' => self::UINT, 'default' => \XF::$time],
            'seeded_until' => ['type' => self::UINT, 'default' => 0],
            'hit_and_run' => ['type' => self::BOOL, 'default' => false]
        ];
        
        $structure->getters = [
            'seed_time' => true,
            'is_seeding' => true,
            'is_exempt_from_hnr' => true,
            'hit_and_run_status' => true
        ];
        
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Torrent' => [
                'entity' => 'XBTTracker:Torrent',
                'type' => self::TO_ONE,
                'conditions' => 'torrent_id',
                'primary' => true
            ],
            'ActivePeer' => [
                'entity' => 'XBTTracker:Peer',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['user_id', '=', '$user_id'],
                    ['torrent_id', '=', '$torrent_id']
                ]
            ]
        ];
        
        return $structure;
    }
    
    /**
     * Get the total time the user has seeded this torrent
     * الحصول على إجمالي الوقت الذي قام فيه المستخدم بزراعة هذا التورنت
     * 
     * @return int Time in seconds
     */
    public function getSeedTime()
    {
        if (!$this->seeded_until) {
            return 0;
        }
        
        return max(0, $this->seeded_until - $this->date);
    }
    
    /**
     * Check if the user is currently seeding this torrent
     * التحقق مما إذا كان المستخدم يقوم حاليًا بزراعة هذا التورنت
     * 
     * @return bool
     */
    public function getIsSeeding()
    {
        /** @var \Harment\XBTTracker\Entity\Peer|null $peer */
        $peer = $this->ActivePeer;
        
        return ($peer && $peer->seeder);
    }
    
    /**
     * Check if the user is exempt from Hit and Run rules for this torrent
     * التحقق مما إذا كان المستخدم معفى من قواعد الضرب والهرب لهذا التورنت
     * 
     * @param array $options Configuration options
     * @return bool
     */
    public function getIsExemptFromHnr(array $options = [])
    {
        // Default options
        $options = array_replace([
            'min_seed_time' => 172800, // 48 hours in seconds
            'min_ratio' => 1.0,
            'check_user_group' => true
        ], $options);
        
        // Current seeding exempts from HnR
        if ($this->getIsSeeding()) {
            return true;
        }
        
        // Exempted if seeded long enough
        if ($this->getSeedTime() >= $options['min_seed_time']) {
            return true;
        }
        
        // Exempted user groups (staff, etc.)
        if ($options['check_user_group'] && $this->User) {
            $exemptGroups = \XF::options()->xbt_hnr_exempt_groups ?: [];
            
            // Secondary groups check
            $secondaryGroups = $this->User->secondary_group_ids ?: [];
            $intersection = array_intersect($exemptGroups, $secondaryGroups);
            
            if (in_array($this->User->user_group_id, $exemptGroups) || !empty($intersection)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the hit and run status
     * الحصول على حالة الضرب والهرب
     * 
     * @return string Status identifier ('none', 'potential', 'warned', 'marked')
     */
    public function getHitAndRunStatus()
    {
        if ($this->getIsExemptFromHnr()) {
            return 'none';
        }
        
        if ($this->hit_and_run) {
            return 'marked';
        }
        
        /** @var \Harment\XBTTracker\Entity\Peer|null $peer */
        $peer = $this->ActivePeer;
        if ($peer && $peer->hit_and_run_warned) {
            return 'warned';
        }
        
        if ($this->getSeedTime() < \XF::options()->xbt_hnr_min_seed_time) {
            return 'potential';
        }
        
        return 'none';
    }
    
    /**
     * Format the seed time in a human-readable format
     * تنسيق وقت البذر بتنسيق مقروء للإنسان
     * 
     * @return string
     */
    public function getFormattedSeedTime()
    {
        $time = $this->getSeedTime();
        
        if ($time < 60) {
            return $time . ' seconds';
        } else if ($time < 3600) {
            $minutes = floor($time / 60);
            return $minutes . ' ' . \XF::phrase('xf_xbt_minutes');
        } else if ($time < 86400) {
            $hours = floor($time / 3600);
            return $hours . ' ' . \XF::phrase('xf_xbt_hours');
        } else {
            $days = floor($time / 86400);
            return $days . ' ' . \XF::phrase('xf_xbt_days');
        }
    }
}