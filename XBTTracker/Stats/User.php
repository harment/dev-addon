<?php

namespace Harment\XBTTracker\Stats;

/**
 * Class User
 * Handler for user-related statistics
 */
class User
{
    /**
     * Get user's ratio value for member stats
     *
     * @param mixed $user User data
     * @return float
     */
    public static function getRatioValue($user)
    {
        $userId = null;
        
        // التعامل مع أنواع مختلفة من المعاملات
        if (is_array($user) && isset($user['user_id'])) {
            $userId = $user['user_id'];
        } elseif (is_object($user) && method_exists($user, 'get')) {
            $userId = $user->get('user_id');
        } elseif (is_object($user) && property_exists($user, 'user_id')) {
            $userId = $user->user_id;
        }
        
        if (empty($userId)) {
            return 0;
        }

        $db = \XF::db();
        $stats = $db->fetchRow("
            SELECT uploaded, downloaded 
            FROM xf_xbt_user_stats 
            WHERE user_id = ?
        ", [$userId]);

        if (!$stats || empty($stats['downloaded'])) {
            return 0;
        }

        return round($stats['uploaded'] / $stats['downloaded'], 2);
    }

    /**
     * Get user's uploaded data amount for member stats
     *
     * @param mixed $user User data
     * @return int
     */
    public static function getUploadedValue($user)
    {
        $userId = null;
        
        // التعامل مع أنواع مختلفة من المعاملات
        if (is_array($user) && isset($user['user_id'])) {
            $userId = $user['user_id'];
        } elseif (is_object($user) && method_exists($user, 'get')) {
            $userId = $user->get('user_id');
        } elseif (is_object($user) && property_exists($user, 'user_id')) {
            $userId = $user->user_id;
        }
        
        if (empty($userId)) {
            return 0;
        }

        $db = \XF::db();
        $uploaded = $db->fetchOne("
            SELECT uploaded 
            FROM xf_xbt_user_stats 
            WHERE user_id = ?
        ", [$userId]);

        return $uploaded ?: 0;
    }

    /**
     * Get user's downloaded data amount for member stats
     *
     * @param mixed $user User data
     * @return int
     */
    public static function getDownloadedValue($user)
    {
        $userId = null;
        
        // التعامل مع أنواع مختلفة من المعاملات
        if (is_array($user) && isset($user['user_id'])) {
            $userId = $user['user_id'];
        } elseif (is_object($user) && method_exists($user, 'get')) {
            $userId = $user->get('user_id');
        } elseif (is_object($user) && property_exists($user, 'user_id')) {
            $userId = $user->user_id;
        }
        
        if (empty($userId)) {
            return 0;
        }

        $db = \XF::db();
        $downloaded = $db->fetchOne("
            SELECT downloaded 
            FROM xf_xbt_user_stats 
            WHERE user_id = ?
        ", [$userId]);

        return $downloaded ?: 0;
    }

    /**
     * Get user's bonus points for member stats
     *
     * @param mixed $user User data
     * @return float
     */
    public static function getBonusPointsValue($user)
    {
        $userId = null;
        
        // التعامل مع أنواع مختلفة من المعاملات
        if (is_array($user) && isset($user['user_id'])) {
            $userId = $user['user_id'];
        } elseif (is_object($user) && method_exists($user, 'get')) {
            $userId = $user->get('user_id');
        } elseif (is_object($user) && property_exists($user, 'user_id')) {
            $userId = $user->user_id;
        }
        
        if (empty($userId)) {
            return 0;
        }

        $db = \XF::db();
        $bonusPoints = $db->fetchOne("
            SELECT bonus_points 
            FROM xf_xbt_user_stats 
            WHERE user_id = ?
        ", [$userId]);

        return $bonusPoints ?: 0;
    }

    /**
     * Get user's torrent count for member stats
     *
     * @param mixed $user User data
     * @return int
     */
    public static function getTorrentCountValue($user)
    {
        $userId = null;
        
        // التعامل مع أنواع مختلفة من المعاملات
        if (is_array($user) && isset($user['user_id'])) {
            $userId = $user['user_id'];
        } elseif (is_object($user) && method_exists($user, 'get')) {
            $userId = $user->get('user_id');
        } elseif (is_object($user) && property_exists($user, 'user_id')) {
            $userId = $user->user_id;
        }
        
        if (empty($userId)) {
            return 0;
        }

        $db = \XF::db();
        $count = $db->fetchOne("
            SELECT COUNT(*) 
            FROM xf_xbt_torrents 
            WHERE user_id = ?
        ", [$userId]);

        return $count ?: 0;
    }

    /**
     * Format data size for display
     *
     * @param int $bytes Data size in bytes
     * @return string Formatted size
     */
    public static function formatSize($bytes)
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } elseif ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes < 1099511627776) {
            return round($bytes / 1073741824, 2) . ' GB';
        } else {
            return round($bytes / 1099511627776, 2) . ' TB';
        }
    }
}