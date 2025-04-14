<?php
// src/addons/XBTTracker/Widget/UserStats.php
namespace XBTTracker\Widget;

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
        return [];
    }
    
    /**
     * Render the widget
     *
     * @return string|array
     */
    public function render()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->user_id) {
            return '';
        }
        
        /** @var \XBTTracker\Repository\UserStats $userStatsRepo */
        $userStatsRepo = $this->repository('XBTTracker:UserStats');
        $userStats = $userStatsRepo->getUserStats($visitor->user_id);
        
        // Get active downloads and seeds
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
            'style' => $this->style
        ];
        
        return $this->renderer('xbt_widget_user_stats', $viewParams);
    }
}