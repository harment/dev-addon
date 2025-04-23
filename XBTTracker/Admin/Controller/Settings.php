<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;

class Settings extends AbstractController
{
    /**
     * Settings index page
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionIndex()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $options = $this->app()->options();
        
        $viewParams = [
            'options' => [
                'announceUrl' => $options->xbtTrackerAnnounceURL ?? $options->xbtTrackerAnnounceUrl ?? '',
                'requiredRatio' => $options->xbtTrackerRequiredRatio ?? 0,
                'ratioExemptGroups' => $options->xbtTrackerRatioExemptGroups ?? [],
                'hitAndRunHours' => $options->xbtTrackerHitAndRunHours ?? 72,
                'torrentPath' => $options->xbtTrackerTorrentPath ?? 'data/torrents',
                'tmdbApiKey' => $options->xbtTrackerTmdbApiKey ?? '',
                'globalFreeleech' => $options->xbtTrackerGlobalFreeleech ?? false,
                'forceThankYou' => $options->xbtTrackerForceThankYou ?? false,
                'autoUpdateStats' => $options->xbtTrackerAutoUpdateStats ?? true,
                'bonusPointsPerHour' => $options->xbtTrackerBonusPointsPerHour ?? 5
            ],
            'userGroups' => $this->getUserGroups()
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Settings', 'harment_xbttracker_admin_settings', $viewParams);
    }
    
    /**
     * Handle settings update
     *
     * @return \XF\Mvc\Reply\Redirect
     */
    public function actionUpdate()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $input = $this->filter([
            'announceUrl' => 'str',
            'requiredRatio' => 'float',
            'ratioExemptGroups' => 'array-uint',
            'hitAndRunHours' => 'uint',
            'torrentPath' => 'str',
            'tmdbApiKey' => 'str',
            'globalFreeleech' => 'bool',
            'forceThankYou' => 'bool',
            'autoUpdateStats' => 'bool',
            'bonusPointsPerHour' => 'uint'
        ]);
        
        $form = $this->formAction();
        
        // Log original values for changes
        $originalValues = [];
        foreach ($this->getSettingOptions() as $key => $optionName) {
            $originalValues[$key] = $this->app()->options()->{$optionName};
        }
        
        // Update options
        $form->basicEntitySave($this->em()->create('XF:Option', 'xbtTrackerAnnounceURL'), [
            'option_value' => $input['announceUrl']
        ]);
        
        $form->basicEntitySave($this->em()->create('XF:Option', 'xbtTrackerRequiredRatio'), [
            'option_value' => $input['requiredRatio']
        ]);
        
        $form->basicEntitySave($this->em()->create('XF:Option', 'xbtTrackerRatioExemptGroups'), [
            'option_value' => $input['ratioExemptGroups']
        ]);
        
        $form->basicEntitySave($this->em()->create('XF:Option', 'xbtTrackerHitAndRunHours'), [
            'option_value' => $input['hitAndRunHours']
        ]);
        
        $form->basicEntitySave($this->em()->create('XF:Option', 'xbtTrackerTorrentPath'), [
            'option_value' => $input['torrentPath']
        ]);
        
        $form->basicEntitySave($this->em()->create('XF:Option', 'xbtTrackerTmdbApiKey'), [
            'option_value' => $input['tmdbApiKey']
        ]);
        
        $form->basicEntitySave($this->em()->create('XF:Option', 'xbtTrackerGlobalFreeleech'), [
            'option_value' => $input['globalFreeleech']
        ]);
        
        $form->basicEntitySave($this->em()->create('XF:Option', 'xbtTrackerForceThankYou'), [
            'option_value' => $input['forceThankYou']
        ]);
        
        $form->basicEntitySave($this->em()->create('XF:Option', 'xbtTrackerAutoUpdateStats'), [
            'option_value' => $input['autoUpdateStats']
        ]);
        
        $form->basicEntitySave($this->em()->create('XF:Option', 'xbtTrackerBonusPointsPerHour'), [
            'option_value' => $input['bonusPointsPerHour']
        ]);
        
        // Log changes
        $changes = [];
        foreach ($this->getSettingOptions() as $key => $optionName) {
            $newValue = $input[$key];
            $oldValue = $originalValues[$key];
            
            if ($newValue !== $oldValue) {
                $changes[] = "$optionName: " . $this->formatValue($oldValue) . " → " . $this->formatValue($newValue);
            }
        }
        
        if ($changes) {
            $this->app()->logger()->info(
                'XBT Tracker settings changed by ' . \XF::visitor()->username . ': ' . implode(', ', $changes)
            );
        }
        
        $form->complete();
        
        return $this->redirect($this->buildLink('tracker/settings'));
    }
    
    /**
     * Create backup of tracker data
     *
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     */
    public function actionCreateBackup()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        if ($this->isPost()) {
            $backupService = $this->service('Harment\XBTTracker:Backup');
            $backupFile = $backupService->createBackup();
            
            if ($backupFile) {
                $this->app()->logger()->info(
                    'XBT Tracker backup created by ' . \XF::visitor()->username . ': ' . $backupFile
                );
                
                return $this->redirect(
                    $this->buildLink('tracker/settings'), 
                    \XF::phrase('harment_xbttracker_backup_created')
                );
            } else {
                return $this->error(\XF::phrase('harment_xbttracker_backup_failed'));
            }
        }
        
        return $this->view('Harment\XBTTracker:Admin\Settings\CreateBackup', 'harment_xbttracker_admin_settings_create_backup');
    }
    
    /**
     * Restore backup of tracker data
     *
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     */
    public function actionRestoreBackup()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $backupDir = \XF::getRootDirectory() . '/data/torrents/backups';
        $backups = [];
        
        if (file_exists($backupDir)) {
            $files = glob($backupDir . '/*.sql');
            foreach ($files as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'date' => filemtime($file),
                    'size' => filesize($file)
                ];
            }
            
            // Sort by date (newest first)
            usort($backups, function($a, $b) {
                return $b['date'] - $a['date'];
            });
        }
        
        if ($this->isPost()) {
            $backup = $this->filter('backup', 'str');
            
            if (!$backup) {
                return $this->error(\XF::phrase('harment_xbttracker_select_backup'));
            }
            
            $backupService = $this->service('Harment\XBTTracker:Backup');
            $success = $backupService->restoreBackup($backup);
            
            if ($success) {
                $this->app()->logger()->info(
                    'XBT Tracker backup restored by ' . \XF::visitor()->username . ': ' . $backup
                );
                
                return $this->redirect(
                    $this->buildLink('tracker/settings'), 
                    \XF::phrase('harment_xbttracker_backup_restored')
                );
            } else {
                return $this->error(\XF::phrase('harment_xbttracker_backup_restore_failed'));
            }
        }
        
        $viewParams = [
            'backups' => $backups
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Settings\RestoreBackup', 'harment_xbttracker_admin_settings_restore_backup', $viewParams);
    }
    
    /**
     * Get mapping between input keys and option names
     *
     * @return array
     */
    protected function getSettingOptions()
    {
        return [
            'announceUrl' => 'xbtTrackerAnnounceURL',
            'requiredRatio' => 'xbtTrackerRequiredRatio',
            'ratioExemptGroups' => 'xbtTrackerRatioExemptGroups',
            'hitAndRunHours' => 'xbtTrackerHitAndRunHours',
            'torrentPath' => 'xbtTrackerTorrentPath',
            'tmdbApiKey' => 'xbtTrackerTmdbApiKey',
            'globalFreeleech' => 'xbtTrackerGlobalFreeleech',
            'forceThankYou' => 'xbtTrackerForceThankYou',
            'autoUpdateStats' => 'xbtTrackerAutoUpdateStats',
            'bonusPointsPerHour' => 'xbtTrackerBonusPointsPerHour'
        ];
    }
    
    /**
     * Format option value for logging
     *
     * @param mixed $value
     * @return string
     */
    protected function formatValue($value)
    {
        if (is_array($value)) {
            if (empty($value)) {
                return 'none';
            }
            return count($value) . ' items';
        } else if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        } else {
            return (string)$value;
        }
    }
    
    /**
     * Get user groups for selection
     *
     * @return array
     */
    protected function getUserGroups()
    {
        $userGroups = [];
        
        $finder = $this->finder('XF:UserGroup')
            ->order('title');
            
        foreach ($finder->fetch() as $group) {
            $userGroups[$group->user_group_id] = $group->title;
        }
        
        return $userGroups;
    }
}