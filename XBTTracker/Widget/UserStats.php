<?php
// src/addons/Harment/XBTTracker/Widget/UserStats.php
namespace Harment\XBTTracker\Widget;

use XF\Widget\AbstractWidget;

/**
 * Widget to display user statistics
 * 
 * @package Harment\XBTTracker\Widget
 */
class UserStats extends AbstractWidget
{
    /**
     * Get default options for this widget
     *
     * @return array Default widget options
     */
    protected function getDefaultOptions()
    {
        return [
            'show_ratio' => true,
            'show_upload_download' => true,
            'show_seeds_leech' => true,
            'show_bonus' => true,
            'show_warnings' => true
        ];
    }
    
    /**
     * Render the widget
     *
     * @return string|array Rendered widget content
     */
    public function render()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->hasPermission('xbtTracker', 'view') || !$visitor->user_id) {
            return '';
        }
        
        /** @var \Harment\XBTTracker\Repository\UserStats $userStatsRepo */
        $userStatsRepo = $this->repository('Harment\XBTTracker:UserStats');
        
        try {
            $userStats = $userStatsRepo->getUserStats($visitor->user_id);
            
            if (!$userStats) {
                $userStats = $userStatsRepo->getOrCreateUserStats($visitor->user_id);
            }
            
            // Get active downloads and seeds for more accurate data
            $activeSeeds = $this->finder('Harment\XBTTracker:Peer')
                ->where([
                    'user_id' => $visitor->user_id,
                    'seeder' => 1
                ])
                ->fetch();
                
            $activeDownloads = $this->finder('Harment\XBTTracker:Peer')
                ->where([
                    'user_id' => $visitor->user_id,
                    'seeder' => 0
                ])
                ->fetch();
            
            $viewParams = [
                'userStats' => $userStats,
                'activeSeeds' => $activeSeeds,
                'activeDownloads' => $activeDownloads,
                'options' => $this->options,
                'style' => $this->style
            ];
            
            return $this->renderer('xbt_widget_user_stats', $viewParams);
        } catch (\Exception $e) {
            \XF::logException($e);
            return '';
        }
    }
    
    /**
     * Get the admin options template
     *
     * @return string Admin options template name
     */
    public function getOptionsTemplate()
    {
        return $this->renderer('admin:xbt_widget_user_stats_options', [
            'options' => $this->options
        ]);
    }
}