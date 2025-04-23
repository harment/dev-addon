<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class User extends AbstractController
{
    /**
     * Display user list with tracker statistics
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionList()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $page = $this->filterPage();
        $perPage = 50;
        
        // Search parameters
        $searchQuery = $this->filter('q', 'str');
        $sortField = $this->filter('sort', 'str', 'username');
        $sortDirection = $this->filter('direction', 'str', 'asc');
        
        $validSorts = ['username', 'uploaded', 'downloaded', 'ratio', 'active_seeds', 'active_leech', 'bonus_points'];
        if (!in_array($sortField, $validSorts)) {
            $sortField = 'username';
        }
        
        $validDirections = ['asc', 'desc'];
        if (!in_array($sortDirection, $validDirections)) {
            $sortDirection = 'desc';
        }
        
        // Get user stats finder
        $userFinder = $this->finder('XF:User')
            ->with('XBTTracker:UserStats');
        
        // Apply search filter
        if ($searchQuery) {
            $userFinder->where('username', 'LIKE', $userFinder->escapeLike($searchQuery) . '%');
        }
        
        // Apply sort
        if ($sortField == 'username') {
            $userFinder->order('username', $sortDirection);
        } else {
            // Order by UserStats fields
            $userFinder->order('UserStats.' . $sortField, $sortDirection);
        }
        
        // Get total count for pagination
        $totalUsers = $userFinder->total();
        
        // Apply pagination
        $userFinder->limitByPage($page, $perPage);
        
        // Get users
        $users = $userFinder->fetch();
        
        $viewParams = [
            'users' => $users,
            'totalUsers' => $totalUsers,
            'page' => $page,
            'perPage' => $perPage,
            'searchQuery' => $searchQuery,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection
        ];
        
        return $this->view('Harment\XBTTracker:Admin\User\List', 'harment_xbttracker_admin_users', $viewParams);
    }
    
    /**
     * Display user details
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     */
    public function actionDetails(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userId = $params->get('user_id');
        $user = $this->assertUserExists($userId);
        
        // Get user stats
        $userStats = $this->getUserStatsRepo()->getOrCreateUserStats($userId);
        
        // Get user torrents
        $torrents = $this->finder('Harment\XBTTracker:Torrent')
            ->where('user_id', $userId)
            ->order('creation_date', 'DESC')
            ->fetch();
            
        // Get user's active peers
        $peers = $this->finder('Harment\XBTTracker:Peer')
            ->where('user_id', $userId)
            ->with('Torrent')
            ->order('last_announce', 'DESC')
            ->fetch();
            
        // Get user's bonus history
        $bonusHistory = $this->finder('Harment\XBTTracker:BonusLog')
            ->where('user_id', $userId)
            ->order('date', 'DESC')
            ->limit(20)
            ->fetch();
            
        // Get user's completed torrents
        $completed = $this->finder('Harment\XBTTracker:UserCompleted')
            ->where('user_id', $userId)
            ->with('Torrent')
            ->order('date', 'DESC')
            ->limit(20)
            ->fetch();
            
        // Calculate ratio if we have downloads
        $ratio = 0;
        if ($userStats->downloaded > 0) {
            $ratio = $userStats->uploaded / $userStats->downloaded;
        }
            
        $viewParams = [
            'user' => $user,
            'userStats' => $userStats,
            'torrents' => $torrents,
            'peers' => $peers,
            'bonusHistory' => $bonusHistory,
            'completed' => $completed,
            'ratio' => $ratio
        ];
        
        return $this->view('Harment\XBTTracker:Admin\User\Details', 'harment_xbttracker_admin_user_details', $viewParams);
    }
    
    /**
     * Edit user details
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     */
    public function actionEdit(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userId = $params->get('user_id');
        $user = $this->assertUserExists($userId);
        
        // Get user stats
        $userStats = $this->getUserStatsRepo()->getOrCreateUserStats($userId);
        
        // Calculate ratio
        $ratio = 0;
        if ($userStats->downloaded > 0) {
            $ratio = $userStats->uploaded / $userStats->downloaded;
        }
        
        $viewParams = [
            'user' => $user,
            'userStats' => $userStats,
            'ratio' => $ratio
        ];
        
        return $this->view('Harment\XBTTracker:Admin\User\Edit', 'harment_xbttracker_admin_user_edit', $viewParams);
    }
    
    /**
     * Save user details
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect
     */
    public function actionEditSave(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userId = $params->get('user_id');
        $user = $this->assertUserExists($userId);
        
        // Get user stats
        $userStats = $this->getUserStatsRepo()->getOrCreateUserStats($userId);
        
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
            'warnings' => $userStats->warnings,
            'active_seeds' => $userStats->active_seeds,
            'active_leech' => $userStats->active_leech
        ];
        
        $userStats->bulkSet($input);
        
        if (!$userStats->save($errors)) {
            return $this->error($errors);
        }
        
        // Log the changes
        $changes = [];
        foreach ($oldValues as $key => $value) {
            if ($value != $userStats->$key) {
                $changes[] = "$key: $value → " . $userStats->$key;
            }
        }
        
        if ($changes) {
            $this->app()->logger()->info(
                'User tracker stats edited for ' . $user->username . ' by ' . \XF::visitor()->username . ': ' . implode(', ', $changes)
            );
        }
        
        return $this->redirect($this->buildLink('tracker/users'));
    }
    
    /**
     * Delete user's tracker data
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionDelete(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userId = $params->get('user_id');
        $user = $this->assertUserExists($userId);
        
        if ($this->isPost()) {
            // Delete user's tracker data
            $db = $this->db();
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Delete user stats
                $db->delete('xf_xbt_user_stats', 'user_id = ?', $userId);
                
                // Delete user's peers
                $db->delete('xf_xbt_peers', 'user_id = ?', $userId);
                
                // Delete user's completed torrents
                $db->delete('xf_xbt_user_completed', 'user_id = ?', $userId);
                
                // Delete user's bonus history
                $db->delete('xf_xbt_user_bonus_history', 'user_id = ?', $userId);
                
                // Optional: Remove passkey from user record
                $db->update('xf_user', ['xbt_passkey' => null], 'user_id = ?', $userId);
                
                // Commit transaction
                $db->commit();
                
                // Log the action
                $this->app()->logger()->info(
                    'User tracker data deleted for ' . $user->username . ' by ' . \XF::visitor()->username
                );
                
                return $this->redirect(
                    $this->buildLink('tracker/users'),
                    \XF::phrase('harment_xbttracker_user_data_deleted')
                );
            } catch (\Exception $e) {
                // Rollback transaction on error
                $db->rollback();
                
                return $this->error(\XF::phrase('harment_xbttracker_error_deleting_user_data') . ': ' . $e->getMessage());
            }
        }
        
        $viewParams = [
            'user' => $user
        ];
        
        return $this->view('Harment\XBTTracker:Admin\User\Delete', 'harment_xbttracker_admin_user_delete', $viewParams);
    }
    
    /**
     * Bulk actions on users
     *
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     */
    public function actionBulkAction()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userIds = $this->filter('user_ids', 'array-uint');
        if (empty($userIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_users_selected'));
        }
        
        $action = $this->filter('action', 'str');
        
        switch ($action) {
            case 'reset_stats':
                return $this->rerouteController(__CLASS__, 'bulkResetStats', [
                    'user_ids' => $userIds
                ]);
                
            case 'reset_passkeys':
                return $this->rerouteController(__CLASS__, 'bulkResetPasskeys', [
                    'user_ids' => $userIds
                ]);
                
            case 'reset_warnings':
                return $this->rerouteController(__CLASS__, 'bulkResetWarnings', [
                    'user_ids' => $userIds
                ]);
                
            case 'delete_data':
                return $this->rerouteController(__CLASS__, 'bulkDeleteData', [
                    'user_ids' => $userIds
                ]);
                
            default:
                return $this->error(\XF::phrase('harment_xbttracker_invalid_bulk_action'));
        }
    }
    
    /**
     * Bulk reset user stats
     *
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionBulkResetStats()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userIds = $this->filter('user_ids', 'array-uint');
        if (empty($userIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_users_selected'));
        }
        
        if ($this->request->exists('confirm')) {
            $db = $this->db();
            
            try {
                $db->update('xf_xbt_user_stats', [
                    'uploaded' => 0,
                    'downloaded' => 0,
                    'bonus_points' => 0,
                    'warnings' => 0,
                    'active_seeds' => 0,
                    'active_leech' => 0
                ], 'user_id IN (' . $db->quote($userIds) . ')');
                
                // Log the action
                $this->app()->logger()->info(
                    'Bulk reset tracker stats for ' . count($userIds) . ' users by ' . \XF::visitor()->username
                );
                
                return $this->redirect(
                    $this->buildLink('tracker/users'),
                    \XF::phrase('harment_xbttracker_user_stats_reset')
                );
            } catch (\Exception $e) {
                return $this->error(\XF::phrase('harment_xbttracker_error_resetting_user_stats') . ': ' . $e->getMessage());
            }
        }
        
        $viewParams = [
            'userIds' => $userIds,
            'userCount' => count($userIds)
        ];
        
        return $this->view('Harment\XBTTracker:Admin\User\BulkResetStats', 'harment_xbttracker_admin_user_bulk_reset_stats', $viewParams);
    }
    
    /**
     * Bulk reset passkeys
     *
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
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
            try {
                foreach ($userIds as $userId) {
                    $userStats = $this->getUserStatsRepo()->getOrCreateUserStats($userId);
                    $userStats->passkey = $this->getUserStatsRepo()->generatePasskey();
                    $userStats->save();
                    
                    // Also update user record for compatibility
                    $user = $this->em()->find('XF:User', $userId);
                    if ($user) {
                        $user->xbt_passkey = $userStats->passkey;
                        $user->save();
                    }
                }
                
                // Log the action
                $this->app()->logger()->info(
                    'Bulk reset passkeys for ' . count($userIds) . ' users by ' . \XF::visitor()->username
                );
                
                return $this->redirect(
                    $this->buildLink('tracker/users'),
                    \XF::phrase('harment_xbttracker_passkeys_reset')
                );
            } catch (\Exception $e) {
                return $this->error(\XF::phrase('harment_xbttracker_error_resetting_passkeys') . ': ' . $e->getMessage());
            }
        }
        
        $viewParams = [
            'userIds' => $userIds,
            'userCount' => count($userIds)
        ];
        
        return $this->view('Harment\XBTTracker:Admin\User\BulkResetPasskeys', 'harment_xbttracker_admin_user_bulk_reset_passkeys', $viewParams);
    }
    
    /**
     * Bulk reset warnings
     *
     * @return \XF\Mvc\Reply\Redirect
     */
    public function actionBulkResetWarnings()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userIds = $this->filter('user_ids', 'array-uint');
        if (empty($userIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_users_selected'));
        }
        
        try {
            $db = $this->db();
            $db->update('xf_xbt_user_stats', 
                ['warnings' => 0],
                'user_id IN (' . $db->quote($userIds) . ')'
            );
            
            // Log the action
            $this->app()->logger()->info(
                'Bulk reset tracker warnings for ' . count($userIds) . ' users by ' . \XF::visitor()->username
            );
            
            return $this->redirect(
                $this->buildLink('tracker/users'),
                \XF::phrase('harment_xbttracker_warnings_reset')
            );
        } catch (\Exception $e) {
            return $this->error(\XF::phrase('harment_xbttracker_error_resetting_warnings') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Bulk delete user data
     *
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionBulkDeleteData()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userIds = $this->filter('user_ids', 'array-uint');
        if (empty($userIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_users_selected'));
        }
        
        if ($this->request->exists('confirm')) {
            $db = $this->db();
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Delete user stats
                $db->delete('xf_xbt_user_stats', 'user_id IN (' . $db->quote($userIds) . ')');
                
                // Delete user's peers
                $db->delete('xf_xbt_peers', 'user_id IN (' . $db->quote($userIds) . ')');
                
                // Delete user's completed torrents
                $db->delete('xf_xbt_user_completed', 'user_id IN (' . $db->quote($userIds) . ')');
                
                // Delete user's bonus history
                $db->delete('xf_xbt_user_bonus_history', 'user_id IN (' . $db->quote($userIds) . ')');
                
                // Optional: Remove passkey from user records
                $db->update('xf_user', ['xbt_passkey' => null], 'user_id IN (' . $db->quote($userIds) . ')');
                
                // Commit transaction
                $db->commit();
                
                // Log the action
                $this->app()->logger()->info(
                    'Bulk deleted tracker data for ' . count($userIds) . ' users by ' . \XF::visitor()->username
                );
                
                return $this->redirect(
                    $this->buildLink('tracker/users'),
                    \XF::phrase('harment_xbttracker_user_data_deleted')
                );
            } catch (\Exception $e) {
                // Rollback transaction on error
                $db->rollback();
                
                return $this->error(\XF::phrase('harment_xbttracker_error_deleting_user_data') . ': ' . $e->getMessage());
            }
        }
        
        $viewParams = [
            'userIds' => $userIds,
            'userCount' => count($userIds)
        ];
        
        return $this->view('Harment\XBTTracker:Admin\User\BulkDeleteData', 'harment_xbttracker_admin_user_bulk_delete_data', $viewParams);
    }
    
    /**
     * Assert user exists
     *
     * @param int $userId
     * @return \XF\Entity\User
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertUserExists($userId)
    {
        $user = $this->em()->find('XF:User', $userId);
        if (!$user) {
            throw $this->exception($this->notFound(\XF::phrase('requested_user_not_found')));
        }
        
        return $user;
    }
    
    /**
     * Get user stats repository
     *
     * @return \Harment\XBTTracker\Repository\UserStats
     */
    protected function getUserStatsRepo()
    {
        return $this->repository('Harment\XBTTracker:UserStats');
    }
}