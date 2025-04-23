<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Warnings extends AbstractController
{
    /**
     * Display warning list
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionList()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $page = $this->filterPage();
        $perPage = 50;
        
        // Filter parameters
        $filters = $this->filter([
            'username' => 'str',
            'date_from' => 'datetime',
            'date_to' => 'datetime',
            'reason' => 'str',
            'is_active' => 'str'
        ]);
        
        // Get warnings finder
        $warningFinder = $this->finder('Harment\XBTTracker:Warning')
            ->with('User')
            ->order('date', 'DESC');
        
        // Apply filters
        if ($filters['username']) {
            $user = $this->finder('XF:User')
                ->where('username', $filters['username'])
                ->fetchOne();
            
            if ($user) {
                $warningFinder->where('user_id', $user->user_id);
            } else {
                // No matching user, return empty result
                $warningFinder->whereOr(['user_id', -1]);
            }
        }
        
        if ($filters['date_from']) {
            $warningFinder->where('date', '>=', $filters['date_from']);
        }
        
        if ($filters['date_to']) {
            $warningFinder->where('date', '<=', $filters['date_to']);
        }
        
        if ($filters['reason']) {
            $warningFinder->where('reason', 'LIKE', '%' . $warningFinder->escapeLike($filters['reason']) . '%');
        }
        
        if ($filters['is_active'] !== '') {
            if ($filters['is_active'] == 'active') {
                $warningFinder->where('is_active', 1);
            } else if ($filters['is_active'] == 'expired') {
                $warningFinder->where('is_active', 0);
            }
        }
        
        // Get total count for pagination
        $totalWarnings = $warningFinder->total();
        
        // Apply pagination
        $warningFinder->limitByPage($page, $perPage);
        
        // Get warnings
        $warnings = $warningFinder->fetch();
        
        $viewParams = [
            'warnings' => $warnings,
            'totalWarnings' => $totalWarnings,
            'page' => $page,
            'perPage' => $perPage,
            'filters' => $filters
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Warnings\List', 'harment_xbttracker_admin_warnings', $viewParams);
    }
    
    /**
     * Issue warning form
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionIssue()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $username = $this->filter('username', 'str');
        $user = null;
        
        if ($username) {
            $user = $this->finder('XF:User')
                ->where('username', $username)
                ->fetchOne();
        }
        
        $viewParams = [
            'username' => $username,
            'user' => $user,
            'predefinedReasons' => $this->getPredefinedWarningReasons()
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Warnings\Issue', 'harment_xbttracker_admin_warning_issue', $viewParams);
    }
    
    /**
     * Handle warning issue
     *
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\Error
     */
    public function actionIssueSave()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $input = $this->filter([
            'username' => 'str',
            'reason' => 'str',
            'points' => 'uint',
            'expiry_date' => 'datetime',
            'torrent_id' => 'uint',
            'is_ratio_warning' => 'bool'
        ]);
        
        if (!$input['username']) {
            return $this->error(\XF::phrase('harment_xbttracker_please_enter_username'));
        }
        
        if (!$input['reason']) {
            return $this->error(\XF::phrase('harment_xbttracker_please_enter_warning_reason'));
        }
        
        // Find user
        $user = $this->finder('XF:User')
            ->where('username', $input['username'])
            ->fetchOne();
            
        if (!$user) {
            return $this->error(\XF::phrase('requested_user_not_found'));
        }
        
        // Create warning
        $warning = $this->em()->create('Harment\XBTTracker:Warning');
        $warning->user_id = $user->user_id;
        $warning->reason = $input['reason'];
        $warning->points = $input['points'];
        $warning->date = \XF::$time;
        $warning->expiry_date = $input['expiry_date'] ?: null;
        $warning->is_active = 1;
        $warning->torrent_id = $input['torrent_id'] ?: null;
        $warning->admin_id = \XF::visitor()->user_id;
        $warning->is_ratio_warning = $input['is_ratio_warning'];
        
        if (!$warning->save($errors)) {
            return $this->error($errors);
        }
        
        // Update user's warning count
        $userStats = $this->getUserStatsRepo()->getOrCreateUserStats($user->user_id);
        $userStats->warnings += 1;
        $userStats->save();
        
        // Send notification to user
        $this->sendWarningNotification($user, $warning);
        
        // Log the action
        $this->app()->logger()->info(
            'Torrent tracker warning issued to ' . $user->username . ' by ' . \XF::visitor()->username . ': ' . $warning->reason
        );
        
        return $this->redirect(
            $this->buildLink('tracker/warnings'),
            \XF::phrase('harment_xbttracker_warning_issued')
        );
    }
    
    /**
     * View warning details
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     */
    public function actionDetails(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $warningId = $params->get('warning_id');
        $warning = $this->assertWarningExists($warningId);
        
        // Get related torrent if any
        $torrent = null;
        if ($warning->torrent_id) {
            $torrent = $this->em()->find('Harment\XBTTracker:Torrent', $warning->torrent_id);
        }
        
        // Get admin who issued the warning
        $admin = null;
        if ($warning->admin_id) {
            $admin = $this->em()->find('XF:User', $warning->admin_id);
        }
        
        $viewParams = [
            'warning' => $warning,
            'user' => $warning->User,
            'torrent' => $torrent,
            'admin' => $admin
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Warnings\Details', 'harment_xbttracker_admin_warning_details', $viewParams);
    }
    
    /**
     * Remove warning (mark as inactive)
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionRemove(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $warningId = $params->get('warning_id');
        $warning = $this->assertWarningExists($warningId);
        
        if ($this->isPost()) {
            // Mark warning as inactive
            $warning->is_active = 0;
            $warning->save();
            
            // Update user's warning count if needed
            if ($warning->is_active) {
                $userStats = $this->getUserStatsRepo()->getUserStats($warning->user_id);
                if ($userStats && $userStats->warnings > 0) {
                    $userStats->warnings -= 1;
                    $userStats->save();
                }
            }
            
            // Log the action
            $this->app()->logger()->info(
                'Torrent tracker warning removed for ' . $warning->User->username . ' by ' . \XF::visitor()->username
            );
            
            return $this->redirect(
                $this->buildLink('tracker/warnings'),
                \XF::phrase('harment_xbttracker_warning_removed')
            );
        }
        
        $viewParams = [
            'warning' => $warning,
            'user' => $warning->User
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Warnings\Remove', 'harment_xbttracker_admin_warning_remove', $viewParams);
    }
    
    /**
     * Delete warning completely
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionDelete(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $warningId = $params->get('warning_id');
        $warning = $this->assertWarningExists($warningId);
        
        if ($this->isPost()) {
            // Update user's warning count if needed
            if ($warning->is_active) {
                $userStats = $this->getUserStatsRepo()->getUserStats($warning->user_id);
                if ($userStats && $userStats->warnings > 0) {
                    $userStats->warnings -= 1;
                    $userStats->save();
                }
            }
            
            // Log user info before deleting
            $username = $warning->User ? $warning->User->username : "User ID: {$warning->user_id}";
            
            // Delete the warning
            $warning->delete();
            
            // Log the action
            $this->app()->logger()->info(
                'Torrent tracker warning deleted for ' . $username . ' by ' . \XF::visitor()->username
            );
            
            return $this->redirect(
                $this->buildLink('tracker/warnings'),
                \XF::phrase('harment_xbttracker_warning_deleted')
            );
        }
        
        $viewParams = [
            'warning' => $warning,
            'user' => $warning->User
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Warnings\Delete', 'harment_xbttracker_admin_warning_delete', $viewParams);
    }
    
    /**
     * Bulk actions on warnings
     *
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     */
    public function actionBulkAction()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $warningIds = $this->filter('warning_ids', 'array-uint');
        if (empty($warningIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_warnings_selected'));
        }
        
        $action = $this->filter('action', 'str');
        
        switch ($action) {
            case 'remove':
                return $this->rerouteController(__CLASS__, 'bulkRemove', [
                    'warning_ids' => $warningIds
                ]);
                
            case 'delete':
                return $this->rerouteController(__CLASS__, 'bulkDelete', [
                    'warning_ids' => $warningIds
                ]);
                
            default:
                return $this->error(\XF::phrase('harment_xbttracker_invalid_bulk_action'));
        }
    }
    
    /**
     * Bulk remove warnings
     *
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionBulkRemove()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $warningIds = $this->filter('warning_ids', 'array-uint');
        if (empty($warningIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_warnings_selected'));
        }
        
        if ($this->request->exists('confirm')) {
            try {
                $db = $this->db();
                
                // Get active warnings to update user stats
                $activeWarnings = $this->finder('Harment\XBTTracker:Warning')
                    ->where('warning_id', $warningIds)
                    ->where('is_active', 1)
                    ->fetch();
                
                // Mark warnings as inactive
                $db->update('xf_xbt_warnings', 
                    ['is_active' => 0],
                    'warning_id IN (' . $db->quote($warningIds) . ')'
                );
                
                // Update user warning counts
                $userCounts = [];
                foreach ($activeWarnings as $warning) {
                    if (!isset($userCounts[$warning->user_id])) {
                        $userCounts[$warning->user_id] = 0;
                    }
                    $userCounts[$warning->user_id]++;
                }
                
                foreach ($userCounts as $userId => $count) {
                    $userStats = $this->getUserStatsRepo()->getUserStats($userId);
                    if ($userStats && $userStats->warnings >= $count) {
                        $userStats->warnings -= $count;
                        $userStats->save();
                    }
                }
                
                // Log the action
                $this->app()->logger()->info(
                    'Bulk removed ' . count($warningIds) . ' tracker warnings by ' . \XF::visitor()->username
                );
                
                return $this->redirect(
                    $this->buildLink('tracker/warnings'),
                    \XF::phrase('harment_xbttracker_warnings_removed')
                );
            } catch (\Exception $e) {
                return $this->error(\XF::phrase('harment_xbttracker_error_removing_warnings') . ': ' . $e->getMessage());
            }
        }
        
        $viewParams = [
            'warningIds' => $warningIds,
            'warningCount' => count($warningIds)
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Warnings\BulkRemove', 'harment_xbttracker_admin_warnings_bulk_remove', $viewParams);
    }
    
    /**
     * Bulk delete warnings
     *
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionBulkDelete()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $warningIds = $this->filter('warning_ids', 'array-uint');
        if (empty($warningIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_warnings_selected'));
        }
        
        if ($this->request->exists('confirm')) {
            try {
                $db = $this->db();
                
                // Get active warnings to update user stats
                $activeWarnings = $this->finder('Harment\XBTTracker:Warning')
                    ->where('warning_id', $warningIds)
                    ->where('is_active', 1)
                    ->fetch();
                
                // Update user warning counts
                $userCounts = [];
                foreach ($activeWarnings as $warning) {
                    if (!isset($userCounts[$warning->user_id])) {
                        $userCounts[$warning->user_id] = 0;
                    }
                    $userCounts[$warning->user_id]++;
                }
                
                // Delete warnings
                $db->delete('xf_xbt_warnings', 
                    'warning_id IN (' . $db->quote($warningIds) . ')'
                );
                
                // Update user stats
                foreach ($userCounts as $userId => $count) {
                    $userStats = $this->getUserStatsRepo()->getUserStats($userId);
                    if ($userStats && $userStats->warnings >= $count) {
                        $userStats->warnings -= $count;
                        $userStats->save();
                    }
                }
                
                // Log the action
                $this->app()->logger()->info(
                    'Bulk deleted ' . count($warningIds) . ' tracker warnings by ' . \XF::visitor()->username
                );
                
                return $this->redirect(
                    $this->buildLink('tracker/warnings'),
                    \XF::phrase('harment_xbttracker_warnings_deleted')
                );
            } catch (\Exception $e) {
                return $this->error(\XF::phrase('harment_xbttracker_error_deleting_warnings') . ': ' . $e->getMessage());
            }
        }
        
        $viewParams = [
            'warningIds' => $warningIds,
            'warningCount' => count($warningIds)
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Warnings\BulkDelete', 'harment_xbttracker_admin_warnings_bulk_delete', $viewParams);
    }
    
    /**
     * Send notification to user about warning
     *
     * @param \XF\Entity\User $user
     * @param \Harment\XBTTracker\Entity\Warning $warning
     */
    protected function sendWarningNotification(\XF\Entity\User $user, $warning)
    {
        if (!$user->email) {
            return;
        }
        
        $mail = $this->app()->mailer()->newMail();
        $mail->setTo($user->email, $user->username);
        $mail->setTemplate('harment_xbttracker_warning_notification', [
            'user' => $user,
            'warning' => $warning,
            'issuer' => \XF::visitor(),
            'torrent' => $warning->torrent_id ? $this->em()->find('Harment\XBTTracker:Torrent', $warning->torrent_id) : null
        ]);
        
        try {
            $mail->send();
        } catch (\Exception $e) {
            // Just log the error but don't fail the warning
            $this->app()->logger()->error('Failed to send warning notification: ' . $e->getMessage());
        }
    }
    
    /**
     * Assert warning exists
     *
     * @param int $warningId
     * @return \Harment\XBTTracker\Entity\Warning
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertWarningExists($warningId)
    {
        $warning = $this->em()->find('Harment\XBTTracker:Warning', $warningId);
        if (!$warning) {
            throw $this->exception($this->notFound(\XF::phrase('harment_xbttracker_warning_not_found')));
        }
        
        return $warning;
    }
    
    /**
     * Get predefined warning reasons
     *
     * @return array
     */
    protected function getPredefinedWarningReasons()
    {
        return [
            'hit_and_run' => [
                'title' => \XF::phrase('harment_xbttracker_hit_and_run'),
                'points' => 1,
                'expiry' => '30D' // 30 days
            ],
            'low_ratio' => [
                'title' => \XF::phrase('harment_xbttracker_low_ratio'),
                'points' => 1,
                'expiry' => '30D' // 30 days
            ],
            'fake_torrent' => [
                'title' => \XF::phrase('harment_xbttracker_fake_torrent'),
                'points' => 3,
                'expiry' => '60D' // 60 days
            ],
            'copyright_violation' => [
                'title' => \XF::phrase('harment_xbttracker_copyright_violation'),
                'points' => 2,
                'expiry' => '45D' // 45 days
            ],
            'torrent_rules_violation' => [
                'title' => \XF::phrase('harment_xbttracker_torrent_rules_violation'),
                'points' => 1,
                'expiry' => '15D' // 15 days
            ]
        ];
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