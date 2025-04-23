<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class UserStats extends AbstractController
{
    /**
     * Display user stats list
     */
    public function actionIndex()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $page = $this->filterPage();
        $perPage = 50;
        
        // Search parameters
        $searchQuery = $this->filter('q', 'str');
        $sortField = $this->filter('sort', 'str', 'uploaded');
        $sortDirection = $this->filter('direction', 'str', 'desc');
        
        $validSorts = ['username', 'uploaded', 'downloaded', 'ratio', 'bonus_points', 'active_seeds', 'active_leech', 'warnings'];
        if (!in_array($sortField, $validSorts)) {
            $sortField = 'uploaded';
        }
        
        $validDirections = ['asc', 'desc'];
        if (!in_array($sortDirection, $validDirections)) {
            $sortDirection = 'desc';
        }
        
        // Get user stats finder
        $userStatsFinder = $this->getUserStatsRepo()->findUserStatsForList()
            ->with('User');
        
        // Apply filters
        if ($searchQuery) {
            $userStatsFinder->whereOr([
                ['User.username', 'LIKE', $userStatsFinder->escapeLike($searchQuery) . '%']
            ]);
        }
        
        // Apply special ratio sorting
        if ($sortField == 'ratio') {
            if ($sortDirection == 'asc') {
                // Special handling for ratio sorting in ascending order
                // First sort by users with 0 download (ratio = ∞)
                $userStatsFinder->whereOr([
                    ['downloaded', '>', 0],
                    ['uploaded', '=', 0]
                ]);
                $userStatsFinder->order(new \XF\Db\Expression('(uploaded / IF(downloaded = 0, 1, downloaded))'), 'ASC');
            } else {
                // Sort by highest ratio first, with 0 downloads last
                $userStatsFinder->order(new \XF\Db\Expression('IF(downloaded = 0, IF(uploaded > 0, 999999999, 0), uploaded / downloaded)'), 'DESC');
            }
        } 
        // Apply username sorting directly on User entity
        else if ($sortField == 'username') {
            $userStatsFinder->order('User.username', $sortDirection);
        }
        // Apply other sort fields
        else {
            $userStatsFinder->order($sortField, $sortDirection);
        }
        
        // Get total count for pagination
        $totalStats = $userStatsFinder->total();
        
        // Apply pagination
        $userStatsFinder->limitByPage($page, $perPage);
        
        // Get user stats
        $userStats = $userStatsFinder->fetch();
        
        $viewParams = [
            'userStats' => $userStats,
            'totalStats' => $totalStats,
            'page' => $page,
            'perPage' => $perPage,
            'searchQuery' => $searchQuery,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection
        ];
        
        return $this->view('Harment\XBTTracker:Admin\UserStats\List', 'harment_xbttracker_admin_user_stats', $viewParams);
    }
    
    /**
     * Edit user stats form
     */
    public function actionEdit(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userId = $params->get('user_id');
        $userStats = $this->assertUserStatsExists($userId);
        
        // Get user information
        $user = $this->em()->find('XF:User', $userId);
        if (!$user) {
            return $this->notFound(\XF::phrase('harment_xbttracker_requested_user_not_found'));
        }
        
        // Calculate ratio
        $ratio = 0;
        if ($userStats->downloaded > 0) {
            $ratio = $userStats->uploaded / $userStats->downloaded;
        }
        
        $viewParams = [
            'userStats' => $userStats,
            'user' => $user,
            'ratio' => $ratio
        ];
        
        return $this->view('Harment\XBTTracker:Admin\UserStats\Edit', 'harment_xbttracker_admin_user_stats_edit', $viewParams);
    }
    
    /**
     * Handle user stats edit
     */
    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userId = $params->get('user_id');
        $userStats = $this->assertUserStatsExists($userId);
        
        $input = $this->filter([
            'uploaded' => 'uint',
            'downloaded' => 'uint',
            'bonus_points' => 'uint',
            'warnings' => 'uint',
            'active_seeds' => 'uint',
            'active_leech' => 'uint'
        ]);
        
        $oldValues = [
            'uploaded' => $userStats->uploaded,
            'downloaded' => $userStats->downloaded,
            'bonus_points' => $userStats->bonus_points,
            'warnings' => $userStats->warnings
        ];
        
        $userStats->bulkSet($input);
        
        if (!$userStats->save($errors)) {
            return $this->error($errors);
        }
        
        // Log the changes
        $user = $this->em()->find('XF:User', $userId);
        $username = $user ? $user->username : "User ID: $userId";
        
        $changes = [];
        foreach ($oldValues as $key => $value) {
            if ($value != $userStats->$key) {
                $changes[] = "$key: $value → " . $userStats->$key;
            }
        }
        
        if ($changes) {
            $this->app()->logger()->info(
                'User stats edited for ' . $username . ' by ' . \XF::visitor()->username . ': ' . implode(', ', $changes)
            );
        }
        
        return $this->redirect($this->buildLink('tracker/user-stats'));
    }
    
    /**
     * Reset passkey confirmation
     */
    public function actionResetPasskey(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userId = $params->get('user_id');
        $userStats = $this->assertUserStatsExists($userId);
        
        // Get user information
        $user = $this->em()->find('XF:User', $userId);
        if (!$user) {
            return $this->notFound(\XF::phrase('harment_xbttracker_requested_user_not_found'));
        }
        
        $viewParams = [
            'userStats' => $userStats,
            'user' => $user
        ];
        
        return $this->view('Harment\XBTTracker:Admin\UserStats\ResetPasskey', 'harment_xbttracker_admin_user_stats_reset_passkey', $viewParams);
    }
    
    /**
     * Handle passkey reset
     */
    public function actionResetPasskeyConfirm(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userId = $params->get('user_id');
        $userStats = $this->assertUserStatsExists($userId);
        
        // Store old passkey for logging
        $oldPasskey = $userStats->passkey;
        
        // Generate new passkey
        $userStats->passkey = $this->getUserStatsRepo()->generatePasskey();
        $userStats->save();
        
        // Log the passkey reset
        $user = $this->em()->find('XF:User', $userId);
        $username = $user ? $user->username : "User ID: $userId";
        
        $this->app()->logger()->info(
            'Passkey reset for ' . $username . ' by ' . \XF::visitor()->username . 
            '. Old: ' . substr($oldPasskey, 0, 8) . '... New: ' . substr($userStats->passkey, 0, 8) . '...'
        );
        
        return $this->redirect($this->buildLink('tracker/user-stats'));
    }
    
    /**
     * Bulk actions on user stats
     */
    public function actionBulk()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userIds = $this->filter('user_ids', 'array-uint');
        if (empty($userIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_users_selected'));
        }
        
        $action = $this->filter('action', 'str');
        
        switch ($action) {
            case 'reset_passkeys':
                return $this->rerouteController(__CLASS__, 'bulkResetPasskeys', [
                    'user_ids' => $userIds
                ]);
                
            case 'reset_warnings':
                return $this->rerouteController(__CLASS__, 'bulkResetWarnings', [
                    'user_ids' => $userIds
                ]);
                
            case 'add_bonus':
                $bonusPoints = $this->filter('bonus_points', 'uint');
                return $this->rerouteController(__CLASS__, 'bulkAddBonus', [
                    'user_ids' => $userIds,
                    'bonus_points' => $bonusPoints
                ]);
                
            default:
                return $this->error(\XF::phrase('harment_xbttracker_invalid_bulk_action'));
        }
    }
    
    /**
     * Bulk reset passkeys
     */
    public function actionBulkResetPasskeys()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userIds = $this->filter('user_ids', 'array-uint');
        if (empty($userIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_users_selected'));
        }
        
        if ($this->request->exists('confirm')) {
            $db = $this->db();
            $userStats = $this->finder('Harment\XBTTracker:UserStats')
                ->whereIds($userIds)
                ->fetch();
                
            foreach ($userStats as $stats) {
                $stats->passkey = $this->getUserStatsRepo()->generatePasskey();
                $stats->save();
            }
            
            // Log the action
            $this->app()->logger()->info(
                'Bulk passkey reset for ' . count($userIds) . ' users by ' . \XF::visitor()->username
            );
            
            return $this->redirect($this->buildLink('tracker/user-stats'));
        } else {
            $viewParams = [
                'userIds' => $userIds,
                'userCount' => count($userIds)
            ];
            
            return $this->view('Harment\XBTTracker:Admin\UserStats\BulkResetPasskeys', 'harment_xbttracker_admin_user_stats_bulk_reset_passkeys', $viewParams);
        }
    }
    
    /**
     * Bulk reset warnings
     */
    public function actionBulkResetWarnings()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userIds = $this->filter('user_ids', 'array-uint');
        if (empty($userIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_users_selected'));
        }
        
        $db = $this->db();
        $db->update(
            'xf_xbt_user_stats',
            ['warnings' => 0],
            'user_id IN (' . $db->quote($userIds) . ')'
        );
        
        // Log the action
        $this->app()->logger()->info(
            'Bulk warning reset for ' . count($userIds) . ' users by ' . \XF::visitor()->username
        );
        
        return $this->redirect($this->buildLink('tracker/user-stats'));
    }
    
    /**
     * Bulk add bonus points
     */
    public function actionBulkAddBonus()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userIds = $this->filter('user_ids', 'array-uint');
        if (empty($userIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_users_selected'));
        }
        
        $bonusPoints = $this->filter('bonus_points', 'uint');
        if ($bonusPoints <= 0) {
            return $this->error(\XF::phrase('harment_xbttracker_invalid_bonus_points'));
        }
        
        if ($this->request->exists('confirm')) {
            $db = $this->db();
            $db->query("
                UPDATE xf_xbt_user_stats 
                SET bonus_points = bonus_points + {$bonusPoints}
                WHERE user_id IN (" . $db->quote($userIds) . ")
            ");
            
            // Log the action
            $this->app()->logger()->info(
                'Bulk added ' . $bonusPoints . ' bonus points to ' . count($userIds) . ' users by ' . \XF::visitor()->username
            );
            
            return $this->redirect($this->buildLink('tracker/user-stats'));
        } else {
            $viewParams = [
                'userIds' => $userIds,
                'userCount' => count($userIds),
                'bonusPoints' => $bonusPoints
            ];
            
            return $this->view('Harment\XBTTracker:Admin\UserStats\BulkAddBonus', 'harment_xbttracker_admin_user_stats_bulk_add_bonus', $viewParams);
        }
    }
    
    /**
     * Assert user stats exists
     */
    protected function assertUserStatsExists($userId)
    {
        $userStats = $this->em()->find('Harment\XBTTracker:UserStats', $userId);
        if (!$userStats) {
            // If user exists but stats don't, create them
            $user = $this->em()->find('XF:User', $userId);
            if ($user) {
                $userStats = $this->getUserStatsRepo()->getOrCreateUserStats($userId);
            } else {
                throw $this->exception($this->notFound(\XF::phrase('harment_xbttracker_requested_user_not_found')));
            }
        }
        
        return $userStats;
    }
    
    /**
     * Get user stats repository
     */
    protected function getUserStatsRepo()
    {
        return $this->repository('Harment\XBTTracker:UserStats');
    }
}