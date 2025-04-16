<?php
// src/addons/XBTTracker/Widget/UserStats.php
namespace Harment\XBTTracker\Widget;

use XF\Widget\AbstractWidget;

/**
 * Widget to display user statistics
 */
class UserStats extends AbstractWidget
{
    /**
     * Get default options for this widget
     *
     * @return array
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
     * @return string|array
     */
    public function render()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->hasPermission('xbtTracker', 'view') || !$visitor->user_id) {
            return '';
        }
        
        /** @var \XBTTracker\Repository\UserStats $userStatsRepo */
        $userStatsRepo = $this->repository('XBTTracker:UserStats');
        
        $userStats = $userStatsRepo->getUserStats($visitor->user_id);
        
        if (!$userStats) {
            $userStats = $userStatsRepo->getOrCreateUserStats($visitor->user_id);
        }
        
        // Get active downloads and seeds for more accurate data
        $activeSeeds = $this->finder('XBTTracker:Peer')
            ->where([
                'user_id' => $visitor->user_id,
                'seeder' => 1
            ])
            ->fetch();
            
        $activeDownloads = $this->finder('XBTTracker:Peer')
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
    }
    
    /**
     * Get the admin options template
     *
     * @return string
     */
    public function getOptionsTemplate()
    {
        return $this->renderer('admin:xbt_widget_user_stats_options', [
            'options' => $this->options
        ]);
    }
}