<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Logs extends AbstractController
{
    /**
     * Display tracker logs
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
            'action' => 'str',
            'user_id' => 'uint',
            'date_from' => 'datetime',
            'date_to' => 'datetime',
            'ip_address' => 'str'
        ]);
        
        // Get logs finder
        $logsFinder = $this->finder('Harment\XBTTracker:Log')
            ->with('User')
            ->order('log_date', 'DESC');
        
        // Apply filters
        if ($filters['action']) {
            $logsFinder->where('action', $filters['action']);
        }
        
        if ($filters['user_id']) {
            $logsFinder->where('user_id', $filters['user_id']);
        }
        
        if ($filters['date_from']) {
            $logsFinder->where('log_date', '>=', $filters['date_from']);
        }
        
        if ($filters['date_to']) {
            $logsFinder->where('log_date', '<=', $filters['date_to']);
        }
        
        if ($filters['ip_address']) {
            $logsFinder->where('ip_address', 'LIKE', $logsFinder->escapeLike($filters['ip_address']) . '%');
        }
        
        // Get total count for pagination
        $totalLogs = $logsFinder->total();
        
        // Apply pagination
        $logsFinder->limitByPage($page, $perPage);
        
        // Get logs
        $logs = $logsFinder->fetch();
        
        // Get action types for filter
        $actionTypes = $this->getLogActionTypes();
        
        $viewParams = [
            'logs' => $logs,
            'totalLogs' => $totalLogs,
            'page' => $page,
            'perPage' => $perPage,
            'filters' => $filters,
            'actionTypes' => $actionTypes
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Logs\List', 'harment_xbttracker_admin_logs', $viewParams);
    }
    
    /**
     * Export logs to CSV
     *
     * @return \XF\Mvc\Reply\View|\XF\Http\Response
     */
    public function actionExport()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        // Filter parameters
        $filters = $this->filter([
            'action' => 'str',
            'user_id' => 'uint',
            'date_from' => 'datetime',
            'date_to' => 'datetime',
            'ip_address' => 'str'
        ]);
        
        if ($this->isPost()) {
            // Get logs finder
            $logsFinder = $this->finder('Harment\XBTTracker:Log')
                ->with('User')
                ->order('log_date', 'DESC');
            
            // Apply filters
            if ($filters['action']) {
                $logsFinder->where('action', $filters['action']);
            }
            
            if ($filters['user_id']) {
                $logsFinder->where('user_id', $filters['user_id']);
            }
            
            if ($filters['date_from']) {
                $logsFinder->where('log_date', '>=', $filters['date_from']);
            }
            
            if ($filters['date_to']) {
                $logsFinder->where('log_date', '<=', $filters['date_to']);
            }
            
            if ($filters['ip_address']) {
                $logsFinder->where('ip_address', 'LIKE', $logsFinder->escapeLike($filters['ip_address']) . '%');
            }
            
            // Get logs
            $logs = $logsFinder->fetch();
            
            // Create CSV
            $csv = "Log ID,Date,User,IP Address,Action,Details\n";
            
            foreach ($logs as $log) {
                $username = $log->User ? $log->User->username : 'Guest';
                $date = date('Y-m-d H:i:s', $log->log_date);
                
                // Escape CSV values
                $csv .= $log->log_id . ',"' . $date . '","' . 
                    str_replace('"', '""', $username) . '","' . 
                    $log->ip_address . '","' . 
                    str_replace('"', '""', $log->action) . '","' . 
                    str_replace('"', '""', $log->details) . "\"\n";
            }
            
            // Set headers for download
            $fileName = 'xbt_tracker_logs_' . date('Y-m-d') . '.csv';
            
            $response = $this->response();
            $response->header('Content-Type', 'text/csv');
            $response->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
            $response->body($csv);
            
            return $response;
        }
        
        // Get action types for filter
        $actionTypes = $this->getLogActionTypes();
        
        $viewParams = [
            'filters' => $filters,
            'actionTypes' => $actionTypes
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Logs\Export', 'harment_xbttracker_admin_logs_export', $viewParams);
    }
    
    /**
     * Clear logs
     *
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionClear()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        if ($this->isPost()) {
            $type = $this->filter('type', 'str');
            $cutOff = $this->filter('cut_off', 'uint');
            
            $db = $this->db();
            $affected = 0;
            
            switch ($type) {
                case 'all':
                    $affected = $db->delete('xf_xbt_log');
                    break;
                    
                case 'older_than':
                    if (!$cutOff) {
                        return $this->error(\XF::phrase('harment_xbttracker_please_specify_days'));
                    }
                    
                    $cutOffDate = time() - ($cutOff * 86400); // days to seconds
                    $affected = $db->delete('xf_xbt_log', 'log_date < ?', $cutOffDate);
                    break;
                    
                default:
                    return $this->error(\XF::phrase('harment_xbttracker_invalid_clear_type'));
            }
            
            // Log the action
            $this->app()->logger()->info(
                'XBT Tracker logs cleared by ' . \XF::visitor()->username . ' (' . $affected . ' entries removed)'
            );
            
            return $this->redirect(
                $this->buildLink('tracker/logs'), 
                \XF::phrase('harment_xbttracker_logs_cleared')
            );
        }
        
        return $this->view('Harment\XBTTracker:Admin\Logs\Clear', 'harment_xbttracker_admin_logs_clear');
    }
    
    /**
     * Test TMDB API connection
     *
     * @return \XF\Mvc\Reply\Redirect
     */
    public function actionTestTmdbApi()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $tmdbApiKey = $this->options()->xbtTrackerTmdbApiKey;
        
        if (!$tmdbApiKey) {
            return $this->error(\XF::phrase('harment_xbttracker_tmdb_api_key_not_set'));
        }
        
        try {
            $tmdbService = $this->service('Harment\XBTTracker:Tmdb');
            $result = $tmdbService->testConnection();
            
            if ($result['success']) {
                return $this->redirect(
                    $this->buildLink('tracker/settings'), 
                    \XF::phrase('harment_xbttracker_tmdb_api_connection_successful')
                );
            } else {
                return $this->error(\XF::phrase('harment_xbttracker_tmdb_api_connection_failed') . ': ' . $result['error']);
            }
        } catch (\Exception $e) {
            return $this->error(\XF::phrase('harment_xbttracker_tmdb_api_connection_failed') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Test mail settings
     *
     * @return \XF\Mvc\Reply\Redirect
     */
    public function actionTestMailSettings()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $visitor = \XF::visitor();
        
        try {
            $mail = $this->app()->mailer()->newMail();
            $mail->setTo($visitor->email, $visitor->username);
            $mail->setTemplate('harment_xbttracker_test_email', [
                'user' => $visitor
            ]);
            $mail->queue();
            
            return $this->redirect(
                $this->buildLink('tracker/settings'), 
                \XF::phrase('harment_xbttracker_test_email_sent')
            );
        } catch (\Exception $e) {
            return $this->error(\XF::phrase('harment_xbttracker_test_email_failed') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Get log action types
     *
     * @return array
     */
    protected function getLogActionTypes()
    {
        return [
            'announce' => \XF::phrase('harment_xbttracker_log_action_announce'),
            'scrape' => \XF::phrase('harment_xbttracker_log_action_scrape'),
            'upload' => \XF::phrase('harment_xbttracker_log_action_upload'),
            'download' => \XF::phrase('harment_xbttracker_log_action_download'),
            'delete' => \XF::phrase('harment_xbttracker_log_action_delete'),
            'edit' => \XF::phrase('harment_xbttracker_log_action_edit'),
            'login' => \XF::phrase('harment_xbttracker_log_action_login'),
            'warning' => \XF::phrase('harment_xbttracker_log_action_warning'),
            'bonus' => \XF::phrase('harment_xbttracker_log_action_bonus'),
            'passkey' => \XF::phrase('harment_xbttracker_log_action_passkey'),
            'settings' => \XF::phrase('harment_xbttracker_log_action_settings'),
            'error' => \XF::phrase('harment_xbttracker_log_action_error')
        ];
    }
}