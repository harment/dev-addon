<?php

namespace Harment\XBTTracker\XF\Repository;

class User extends XFCP_User
{
    /**
     * Get users with torrent statistics
     *
     * @param array $conditions
     * @param array $fetchOptions
     * @return \XF\Mvc\Entity\Finder
     */
    public function findTorrentUsers(array $conditions = [], array $fetchOptions = [])
    {
        $finder = $this->finder('XF:User');
        
        $finder->with('XBTTracker:UserStats');
        
        foreach ($conditions as $field => $value) {
            $finder->where($field, $value);
        }
        
        return $finder;
    }
    
    /**
     * Get top uploaders
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getTopUploaders($limit = 10)
    {
        $db = $this->db();
        
        $userIds = $db->fetchAllColumn("
            SELECT t.user_id
            FROM xf_xbt_torrents AS t
            GROUP BY t.user_id
            ORDER BY COUNT(*) DESC
            LIMIT ?
        ", $limit);
        
        if (!$userIds) {
            return $this->em->getEmptyCollection();
        }
        
        return $this->finder('XF:User')
            ->where('user_id', $userIds)
            ->fetch();
    }
    
    /**
     * Get top seeders
     *
     * @param int $limit
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getTopSeeders($limit = 10)
    {
        $db = $this->db();
        
        $userIds = $db->fetchAllColumn("
            SELECT user_id
            FROM xf_xbt_user_stats
            ORDER BY active_seeds DESC
            LIMIT ?
        ", $limit);
        
        if (!$userIds) {
            return $this->em->getEmptyCollection();
        }
        
        return $this->finder('XF:User')
            ->where('user_id', $userIds)
            ->fetch();
    }
}
