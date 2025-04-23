<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class BonusPoints extends AbstractController
{
    /**
     * Display bonus points overview
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionList()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        // Get bonus points statistics
        $stats = $this->getBonusPointsStats();
        
        // Get top users with bonus points
        $topUsers = $this->getUserStatsRepo()
            ->findUserStatsForList()
            ->with('User')
            ->order('bonus_points', 'DESC')
            ->limit(10)
            ->fetch();
        
        // Get recent bonus point logs
        $recentLogs = $this->finder('Harment\XBTTracker:BonusLog')
            ->with('User')
            ->order('date', 'DESC')
            ->limit(20)
            ->fetch();
        
        $viewParams = [
            'stats' => $stats,
            'topUsers' => $topUsers,
            'recentLogs' => $recentLogs
        ];
        
        return $this->view('Harment\XBTTracker:Admin\BonusPoints\List', 'harment_xbttracker_admin_bonus_points', $viewParams);
    }
    
    /**
     * Display all user bonus points
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionAll()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $page = $this->filterPage();
        $perPage = 50;
        
        // Search parameters
        $searchQuery = $this->filter('q', 'str');
        $sortField = $this->filter('sort', 'str', 'bonus_points');
        $sortDirection = $this->filter('direction', 'str', 'desc');
        
        $validSorts = ['username', 'bonus_points', 'uploaded', 'downloaded', 'ratio'];
        if (!in_array($sortField, $validSorts)) {
            $sortField = 'bonus_points';
        }
        
        $validDirections = ['asc', 'desc'];
        if (!in_array($sortDirection, $validDirections)) {
            $sortDirection = 'desc';
        }
        
        // Get user stats finder
        $userStatsFinder = $this->getUserStatsRepo()->findUserStatsForList()
            ->with('User');
        
        // Apply search filter
        if ($searchQuery) {
            $userStatsFinder->whereOr([
                ['User.username', 'LIKE', $userStatsFinder->escapeLike($searchQuery) . '%']
            ]);
        }
        
        // Apply sort
        if ($sortField == 'username') {
            $userStatsFinder->order('User.username', $sortDirection);
        } else if ($sortField == 'ratio') {
            if ($sortDirection == 'asc') {
                $userStatsFinder->whereOr([
                    ['downloaded', '>', 0],
                    ['uploaded', '=', 0]
                ]);
                $userStatsFinder->order(new \XF\Db\Expression('(uploaded / IF(downloaded = 0, 1, downloaded))'), 'ASC');
            } else {
                $userStatsFinder->order(new \XF\Db\Expression('IF(downloaded = 0, IF(uploaded > 0, 999999999, 0), uploaded / downloaded)'), 'DESC');
            }
        } else {
            $userStatsFinder->order($sortField, $sortDirection);
        }
        
        // Get total count for pagination
        $totalUsers = $userStatsFinder->total();
        
        // Apply pagination
        $userStatsFinder->limitByPage($page, $perPage);
        
        // Get user stats
        $userStats = $userStatsFinder->fetch();
        
        $viewParams = [
            'userStats' => $userStats,
            'totalUsers' => $totalUsers,
            'page' => $page,
            'perPage' => $perPage,
            'searchQuery' => $searchQuery,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection
        ];
        
        return $this->view('Harment\XBTTracker:Admin\BonusPoints\All', 'harment_xbttracker_admin_bonus_points_all', $viewParams);
    }
    
    /**
     * Award bonus points to specific users
     *
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionAward()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        if ($this->isPost()) {
            $input = $this->filter([
                'usernames' => 'str',
                'points' => 'uint',
                'reason' => 'str'
            ]);
            
            // Validate input
            if (!$input['usernames']) {
                return $this->error(\XF::phrase('harment_xbttracker_please_enter_usernames'));
            }
            
            if (!$input['points']) {
                return $this->error(\XF::phrase('harment_xbttracker_please_enter_points'));
            }
            
            // Split usernames
            $usernames = preg_split('/\s*,\s*/', trim($input['usernames']), -1, PREG_SPLIT_NO_EMPTY);
            if (!$usernames) {
                return $this->error(\XF::phrase('harment_xbttracker_please_enter_usernames'));
            }
            
            // Get user IDs
            $users = $this->finder('XF:User')
                ->where('username', $usernames)
                ->fetch();
                
            if (!$users->count()) {
                return $this->error(\XF::phrase('harment_xbttracker_no_matching_users_found'));
            }
            
            // Award bonus points
            $awarded = [];
            $db = $this->db();
            
            foreach ($users as $user) {
                // Get or create user stats
                $userStats = $this->getUserStatsRepo()->getOrCreateUserStats($user->user_id);
                
                // Update bonus points
                $userStats->bonus_points += $input['points'];
                $userStats->save();
                
                // Log bonus points award
                $this->insertBonusLog($user->user_id, $input['points'], $input['reason'] ?: \XF::phrase('harment_xbttracker_awarded_by_admin'));
                
                $awarded[] = $user->username;
            }
            
            // Log admin action
            $this->app()->logger()->info(
                'Bonus points awarded by ' . \XF::visitor()->username . ': ' . 
                $input['points'] . ' points to ' . implode(', ', $awarded) . 
                ($input['reason'] ? ' (' . $input['reason'] . ')' : '')
            );
            
            return $this->redirect(
                $this->buildLink('tracker/bonus-points'),
                \XF::phrase('harment_xbttracker_bonus_points_awarded')
            );
        }
        
        return $this->view('Harment\XBTTracker:Admin\BonusPoints\Award', 'harment_xbttracker_admin_bonus_points_award');
    }
    
    /**
     * Award bonus points to all users
     *
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionAwardAll()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        if ($this->isPost()) {
            $input = $this->filter([
                'points' => 'uint',
                'reason' => 'str',
                'min_ratio' => 'float',
                'exclude_unconfirmed' => 'bool',
                'exclude_banned' => 'bool'
            ]);
            
            if (!$input['points']) {
                return $this->error(\XF::phrase('harment_xbttracker_please_enter_points'));
            }
            
            // Build query to get qualifying users
            $userFinder = $this->finder('XF:User');
            
            if ($input['exclude_unconfirmed']) {
                $userFinder->where('user_state', 'valid');
            }
            
            if ($input['exclude_banned']) {
                $userFinder->where('is_banned', 0);
            }
            
            $users = $userFinder->fetch();
            $userIds = $users->keys();
            
            if (empty($userIds)) {
                return $this->error(\XF::phrase('harment_xbttracker_no_qualifying_users_found'));
            }
            
            // Award bonus points
            $db = $this->db();
            $stats = $this->getUserStatsRepo()->findUserStatsForList()
                ->whereIds($userIds);
                
            // Apply ratio filter if needed
            if ($input['min_ratio'] > 0) {
                $stats->where(new \XF\Db\Expression('(IF(downloaded = 0, IF(uploaded > 0, 999999999, 0), uploaded / downloaded) >= ' . floatval($input['min_ratio']) . ')'));
            }
            
            $stats = $stats->fetch();
            $count = 0;
            
            foreach ($stats as $userStat) {
                // Update bonus points
                $userStat->bonus_points += $input['points'];
                $userStat->save();
                
                // Log bonus points award
                $this->insertBonusLog($userStat->user_id, $input['points'], $input['reason'] ?: \XF::phrase('harment_xbttracker_mass_award'));
                
                $count++;
            }
            
            // Log admin action
            $this->app()->logger()->info(
                'Mass bonus points awarded by ' . \XF::visitor()->username . ': ' . 
                $input['points'] . ' points to ' . $count . ' users' .
                ($input['reason'] ? ' (' . $input['reason'] . ')' : '')
            );
            
            return $this->redirect(
                $this->buildLink('tracker/bonus-points'),
                \XF::phrase('harment_xbttracker_bonus_points_awarded_to_x_users', ['count' => $count])
            );
        }
        
        return $this->view('Harment\XBTTracker:Admin\BonusPoints\AwardAll', 'harment_xbttracker_admin_bonus_points_award_all');
    }
    
    /**
     * Award bonus points by ratio
     *
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionAwardByRatio()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        if ($this->isPost()) {
            $input = $this->filter([
                'min_ratio' => 'float',
                'max_ratio' => 'float',
                'points' => 'uint',
                'reason' => 'str'
            ]);
            
            if (!$input['points']) {
                return $this->error(\XF::phrase('harment_xbttracker_please_enter_points'));
            }
            
            if ($input['min_ratio'] < 0 || $input['max_ratio'] < 0) {
                return $this->error(\XF::phrase('harment_xbttracker_ratio_must_be_positive'));
            }
            
            if ($input['max_ratio'] > 0 && $input['min_ratio'] > $input['max_ratio']) {
                return $this->error(\XF::phrase('harment_xbttracker_min_ratio_must_be_less_than_max'));
            }
            
            // Build query to get qualifying users
            $statsBuilder = $this->getUserStatsRepo()->findUserStatsForList();
            
            // Only users with downloads > 0 to calculate valid ratio
            $statsBuilder->where('downloaded', '>', 0);
            
            if ($input['min_ratio'] > 0) {
                $statsBuilder->where(new \XF\Db\Expression('(uploaded / downloaded) >= ' . floatval($input['min_ratio'])));
            }
            
            if ($input['max_ratio'] > 0) {
                $statsBuilder->where(new \XF\Db\Expression('(uploaded / downloaded) <= ' . floatval($input['max_ratio'])));
            }
            
            $stats = $statsBuilder->fetch();
            $count = 0;
            
            foreach ($stats as $userStat) {
                // Update bonus points
                $userStat->bonus_points += $input['points'];
                $userStat->save();
                
                // Log bonus points award
                $this->insertBonusLog($userStat->user_id, $input['points'], $input['reason'] ?: \XF::phrase('harment_xbttracker_ratio_award'));
                
                $count++;
            }
            
            // Log admin action
            $ratioText = $input['min_ratio'] . ' - ' . ($input['max_ratio'] > 0 ? $input['max_ratio'] : '∞');
            $this->app()->logger()->info(
                'Ratio-based bonus points awarded by ' . \XF::visitor()->username . ': ' . 
                $input['points'] . ' points to ' . $count . ' users with ratio ' . $ratioText .
                ($input['reason'] ? ' (' . $input['reason'] . ')' : '')
            );
            
            return $this->redirect(
                $this->buildLink('tracker/bonus-points'),
                \XF::phrase('harment_xbttracker_bonus_points_awarded_to_x_users', ['count' => $count])
            );
        }
        
        return $this->view('Harment\XBTTracker:Admin\BonusPoints\AwardByRatio', 'harment_xbttracker_admin_bonus_points_award_by_ratio');
    }
    
    /**
     * Adjust bonus points for a specific user
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionAdjust(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userId = $params->get('user_id');
        $user = $this->assertUserExists($userId);
        
        // Get or create user stats
        $userStats = $this->getUserStatsRepo()->getOrCreateUserStats($userId);
        
        if ($this->isPost()) {
            $input = $this->filter([
                'action' => 'str',
                'points' => 'uint',
                'reason' => 'str'
            ]);
            
            if (!$input['points']) {
                return $this->error(\XF::phrase('harment_xbttracker_please_enter_points'));
            }
            
            switch ($input['action']) {
                case 'add':
                    $userStats->bonus_points += $input['points'];
                    $logPoints = $input['points'];
                    $actionText = 'added';
                    break;
                    
                case 'subtract':
                    // Don't go below zero
                    if ($userStats->bonus_points < $input['points']) {
                        $input['points'] = $userStats->bonus_points;
                    }
                    
                    $userStats->bonus_points -= $input['points'];
                    $logPoints = -$input['points'];
                    $actionText = 'subtracted';
                    break;
                    
                case 'set':
                    $oldPoints = $userStats->bonus_points;
                    $userStats->bonus_points = $input['points'];
                    $logPoints = $input['points'] - $oldPoints;
                    $actionText = 'set to';
                    break;
                    
                default:
                    return $this->error(\XF::phrase('harment_xbttracker_invalid_points_action'));
            }
            
            $userStats->save();
            
            // Log bonus points change
            if ($logPoints != 0) {
                $this->insertBonusLog($userId, $logPoints, $input['reason'] ?: \XF::phrase('harment_xbttracker_adjusted_by_admin'));
            }
            
            // Log admin action
            $this->app()->logger()->info(
                'Bonus points ' . $actionText . ' ' . $input['points'] . ' for ' . $user->username . ' by ' . \XF::visitor()->username .
                ($input['reason'] ? ' (' . $input['reason'] . ')' : '')
            );
            
            return $this->redirect(
                $this->buildLink('tracker/user-stats/edit', $user),
                \XF::phrase('harment_xbttracker_bonus_points_adjusted')
            );
        }
        
        $viewParams = [
            'user' => $user,
            'userStats' => $userStats
        ];
        
        return $this->view('Harment\XBTTracker:Admin\BonusPoints\Adjust', 'harment_xbttracker_admin_bonus_points_adjust', $viewParams);
    }
    
    /**
     * View bonus points history for a user
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     */
    public function actionHistory(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $userId = $params->get('user_id');
        $user = $this->assertUserExists($userId);
        
        $page = $this->filterPage();
        $perPage = 50;
        
        // Get history
        $logFinder = $this->finder('Harment\XBTTracker:BonusLog')
            ->where('user_id', $userId)
            ->order('date', 'DESC');
            
        $totalLogs = $logFinder->total();
        
        // Apply pagination
        $logFinder->limitByPage($page, $perPage);
        
        $logs = $logFinder->fetch();
        
        $viewParams = [
            'user' => $user,
            'logs' => $logs,
            'totalLogs' => $totalLogs,
            'page' => $page,
            'perPage' => $perPage
        ];
        
        return $this->view('Harment\XBTTracker:Admin\BonusPoints\History', 'harment_xbttracker_admin_bonus_points_history', $viewParams);
    }
    
    /**
     * View all bonus points logs
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionLog()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $page = $this->filterPage();
        $perPage = 50;
        
        // Filter parameters
        $filters = $this->filter([
            'username' => 'str',
            'date_from' => 'datetime',
            'date_to' => 'datetime',
            'reason' => 'str'
        ]);
        
        // Get logs finder
        $logFinder = $this->finder('Harment\XBTTracker:BonusLog')
            ->with('User')
            ->order('date', 'DESC');
        
        // Apply filters
        if ($filters['username']) {
            $user = $this->finder('XF:User')
                ->where('username', $filters['username'])
                ->fetchOne();
            
            if ($user) {
                $logFinder->where('user_id', $user->user_id);
            } else {
                // No matching user, return empty result
                $logFinder->whereOr(['user_id', -1]);
            }
        }
        
        if ($filters['date_from']) {
            $logFinder->where('date', '>=', $filters['date_from']);
        }
        
        if ($filters['date_to']) {
            $logFinder->where('date', '<=', $filters['date_to']);
        }
        
        if ($filters['reason']) {
            $logFinder->where('reason', 'LIKE', '%' . $logFinder->escapeLike($filters['reason']) . '%');
        }
        
        // Get total count for pagination
        $totalLogs = $logFinder->total();
        
        // Apply pagination
        $logFinder->limitByPage($page, $perPage);
        
        // Get logs
        $logs = $logFinder->fetch();
        
        $viewParams = [
            'logs' => $logs,
            'totalLogs' => $totalLogs,
            'page' => $page,
            'perPage' => $perPage,
            'filters' => $filters
        ];
        
        return $this->view('Harment\XBTTracker:Admin\BonusPoints\Log', 'harment_xbttracker_admin_bonus_points_log', $viewParams);
    }
    
    /**
     * Reset all bonus points
     *
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionResetAll()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        if ($this->isPost()) {
            $confirm = $this->filter('confirm', 'bool');
            
            if (!$confirm) {
                return $this->error(\XF::phrase('harment_xbttracker_please_confirm_reset'));
            }
            
            $db = $this->db();
            $db->update('xf_xbt_user_stats', ['bonus_points' => 0]);
            
            // Log the action
            $this->app()->logger()->info(
                'All bonus points reset by ' . \XF::visitor()->username
            );
            
            return $this->redirect(
                $this->buildLink('tracker/bonus-points'),
                \XF::phrase('harment_xbttracker_all_bonus_points_reset')
            );
        }
        
        return $this->view('Harment\XBTTracker:Admin\BonusPoints\ResetAll', 'harment_xbttracker_admin_bonus_points_reset_all');
    }
    
    /**
     * Get bonus points statistics
     *
     * @return array
     */
    protected function getBonusPointsStats()
    {
        $db = $this->db();
        
        $total = $db->fetchOne("
            SELECT SUM(bonus_points)
            FROM xf_xbt_user_stats
        ") ?: 0;
        
        $average = $db->fetchOne("
            SELECT AVG(bonus_points)
            FROM xf_xbt_user_stats
            WHERE bonus_points > 0
        ") ?: 0;
        
        $usersWithBonusPoints = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_xbt_user_stats
            WHERE bonus_points > 0
        ") ?: 0;
        
        $spentLast24Hours = $db->fetchOne("
            SELECT SUM(ABS(points))
            FROM xf_xbt_user_bonus_history
            WHERE date >= ? AND points < 0
        ", [time() - 86400]) ?: 0;
        
        $earnedLast24Hours = $db->fetchOne("
            SELECT SUM(points)
            FROM xf_xbt_user_bonus_history
            WHERE date >= ? AND points > 0
        ", [time() - 86400]) ?: 0;
        
        $spentLastWeek = $db->fetchOne("
            SELECT SUM(ABS(points))
            FROM xf_xbt_user_bonus_history
            WHERE date >= ? AND points < 0
        ", [time() - 604800]) ?: 0;
        
        $earnedLastWeek = $db->fetchOne("
            SELECT SUM(points)
            FROM xf_xbt_user_bonus_history
            WHERE date >= ? AND points > 0
        ", [time() - 604800]) ?: 0;
        
        return [
            'total' => $total,
            'average' => $average,
            'usersWithBonusPoints' => $usersWithBonusPoints,
            'spentLast24Hours' => $spentLast24Hours,
            'earnedLast24Hours' => $earnedLast24Hours,
            'spentLastWeek' => $spentLastWeek,
            'earnedLastWeek' => $earnedLastWeek
        ];
    }
    
    /**
     * Insert bonus log entry
     *
     * @param int $userId
     * @param int $points
     * @param string $reason
     */
    protected function insertBonusLog($userId, $points, $reason)
    {
        if ($points == 0) {
            return;
        }
        
        $log = $this->em()->create('Harment\XBTTracker:BonusLog');
        $log->user_id = $userId;
        $log->points = $points;
        $log->reason = $reason;
        $log->date = time();
        $log->save();
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