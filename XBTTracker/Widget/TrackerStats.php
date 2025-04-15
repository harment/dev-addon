<?php
// src/addons/XBTTracker/Widget/TrackerStats.php
namespace XBTTracker\Widget;

use XF\Widget\AbstractWidget;

/**
 * Widget to display tracker statistics
 */
class TrackerStats extends AbstractWidget
{
    /**
     * Get default options for this widget
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        return [
            'show_status' => true
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
        
        if (!$visitor->hasPermission('xbtTracker', 'view')) {
            return '';
        }
        
        /** @var \XBTTracker\Repository\Torrent $torrentRepo */
        $torrentRepo = $this->repository('XBTTracker:Torrent');
        
        $trackerStats = $torrentRepo->getTorrentStats();
        $status = 'offline';
        $showStatus = (bool)$this->options['show_status'];
        
        if ($showStatus) {
            // Check if tracker service exists first
            if (\XF::app()->offsetExists('xbt.tracker.service')) {
                /** @var \XBTTracker\Service\Tracker $trackerService */
                $trackerService = \XF::app()->get('xbt.tracker.service');
                if (method_exists($trackerService, 'getTrackerStatus')) {
                    $status = $trackerService->getTrackerStatus() ? 'online' : 'offline';
                }
            } else {
                // Fallback to manual check
                $announceUrl = \XF::options()->xbtTrackerAnnounceURL;
                
                if ($announceUrl) {
                    $parsedUrl = parse_url($announceUrl);
                    if (isset($parsedUrl['host']) && isset($parsedUrl['port'])) {
                        $host = $parsedUrl['host'];
                        $port = $parsedUrl['port'];
                        $connection = @fsockopen($host, $port, $errno, $errstr, 5);
                        if ($connection) {
                            $status = 'online';
                            fclose($connection);
                        }
                    }
                }
            }
        }
        
        $viewParams = [
            'trackerStats' => $trackerStats,
            'trackerStatus' => $status,
            'showStatus' => $showStatus,
            'style' => $this->style
        ];
        
        return $this->renderer('xbt_widget_tracker_stats', $viewParams);
    }
    
    /**
     * Get the admin options template
     *
     * @return string
     */
    public function getOptionsTemplate()
    {
        return $this->renderer('admin:xbt_widget_tracker_stats_options', [
            'options' => $this->options
        ]);
    }
}