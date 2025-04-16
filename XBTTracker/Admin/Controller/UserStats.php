<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class UserStats extends AbstractController
{
    /**
     * Display user stats list
     */
    public function actionList()
    {
        $this->assertAdminPermission('xbtTracker');
        
        $page = $this->filterPage();
        $perPage = 50;
        
        // Search parameters
        $searchQuery = $this->filter('q', 'str');
        $sortField = $this->filter('sort', 'str', 'uploaded');
        $sortDirection = $this->filter('direction', 'str', 'desc');
        
        $validSorts = ['uploaded', 'downloaded', 'bonus_points', 'active_seeds', 'active_leech', 'warnings'];
        if (!in_array($sortField, $validSorts)) {
            $sortField = 'uploaded';
        }
        
        $validDirections = ['asc', 'desc'];
        if (!in_array($sortDirection, $validDirections)) {
            $sortDirection = 'desc';
        }
        
        // Get user stats finder
        $userStatsFinder = $this->getUserStatsRepo()->findUserStatsForList();
        
        // Apply filters
        if ($searchQuery) {
            $users = $this->finder('XF:User')
                ->where('username', 'LIKE', $userStatsFinder->escapeLike($searchQuery) . '%')
                ->fetch(['User.user_id']);
            
            $userIds = $users->keys();
            if ($userIds) {
                $userStatsFinder->where('user_id', $userIds);
            } else {
                $userStatsFinder->whereImpossible();
            }
        }
        
        // Apply sort order
        $userStatsFinder->order($sortField, $sortDirection);
        
        // Get total count for pagination
        $totalStats = $userStatsFinder->total();
        
        // Apply pagination
        $userStatsFinder->limitByPage($page, $perPage);
        
        // Get user stats
        $userStats = $userStatsFinder->fetch();
        
        // Get user information
        $userIds = $userStats->keys();
        $users = [];
        
        if ($userIds) {
            $users = $this->finder('XF:User')
                ->where('user_id', $userIds)
                ->fetch()
                ->toArray();
        }
        
        $viewParams = [
            'userStats' => $userStats,
            'users' => $users,
            'totalStats' => $totalStats,
            'page' => $page,
            'perPage' => $perPage,
            'searchQuery' => $searchQuery,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection
        ];
        
        return $this->view('XBTTracker:Admin\UserStats\List', 'xbt_admin_user_stats', $viewParams);
    }
    
    /**
     * Edit user stats form
     */
    public function actionEdit(ParameterBag $params)
    {
        $this->assertAdminPermission('xbtTracker');
        
        $userId = $params->get('user_id');
        $userStats = $this->assertUserStatsExists($userId);
        
        // Get user information
        $user = $this->em()->find('XF:User', $userId);
        if (!$user) {
            return $this->notFound();
        }
        
        $viewParams = [
            'userStats' => $userStats,
            'user' => $user
        ];
        
        return $this->view('XBTTracker:Admin\UserStats\Edit', 'xbt_admin_user_stats_edit', $viewParams);
    }
    
    /**
     * Handle user stats edit
     */
    public function actionEditSave(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('xbtTracker');
        
        $userId = $params->get('user_id');
        $userStats = $this->assertUserStatsExists($userId);
        
        $input = $this->filter([
            'uploaded' => 'uint',
            'downloaded' => 'uint',
            'bonus_points' => 'uint',
            'warnings' => 'uint'
        ]);
        
        $userStats->bulkSet($input);
        
        if (!$userStats->save($errors)) {
            return $this->error($errors);
        }
        
        return $this->redirect($this->buildLink('torrents/user-stats'));
    }
    
    /**
     * Reset passkey confirmation
     */
    public function actionResetPasskey(ParameterBag $params)
    {
        $this->assertAdminPermission('xbtTracker');
        
        $userId = $params->get('user_id');
        $userStats = $this->assertUserStatsExists($userId);
        
        // Get user information
        $user = $this->em()->find('XF:User', $userId);
        if (!$user) {
            return $this->notFound();
        }
        
        $viewParams = [
            'userStats' => $userStats,
            'user' => $user
        ];
        
        return $this->view('XBTTracker:Admin\UserStats\ResetPasskey', 'xbt_admin_user_stats_reset_passkey', $viewParams);
    }
    
    /**
     * Handle passkey reset
     */
    public function actionResetPasskeyConfirm(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('xbtTracker');
        
        $userId = $params->get('user_id');
        $userStats = $this->assertUserStatsExists($userId);
        
        // Generate new passkey
        $userStats->passkey = $this->getUserStatsRepo()->generatePasskey();
        $userStats->save();
        
        return $this->redirect($this->buildLink('torrents/user-stats'));
    }
    
    /**
     * Assert user stats exists
     */
    protected function assertUserStatsExists($userId)
    {
        $userStats = $this->em()->find('XBTTracker:UserStats', $userId);
        if (!$userStats) {
            // If user exists but stats don't, create them
            $user = $this->em()->find('XF:User', $userId);
            if ($user) {
                $userStats = $this->getUserStatsRepo()->getOrCreateUserStats($userId);
            } else {
                throw $this->exception($this->notFound());
            }
        }
        
        return $userStats;
    }
    
    /**
     * Get user stats repository
     */
    protected function getUserStatsRepo()
    {
        return $this->repository('XBTTracker:UserStats');
    }
}