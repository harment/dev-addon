<?php
// src/addons/Harment/XBTTracker/Widget/TrackerStats.php
namespace Harment\XBTTracker\Widget;

use XF\Widget\AbstractWidget;

/**
 * Widget to display tracker statistics
 * 
 * @package Harment\XBTTracker\Widget
 */
class TrackerStats extends AbstractWidget
{
    /**
     * Get default options for this widget
     *
     * @return array Default widget options
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
     * @return string|array Rendered widget content
     */
    public function render()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->hasPermission('xbtTracker', 'view')) {
            return '';
        }
        
        /** @var \Harment\XBTTracker\Repository\Torrent $torrentRepo */
        $torrentRepo = $this->repository('Harment\XBTTracker:Torrent');
        
        $trackerStats = $torrentRepo->getTorrentStats();
        $status = 'offline';
        $showStatus = (bool)$this->options['show_status'];
        
        if ($showStatus) {
            try {
                // Check if tracker service exists first
                if (\XF::app()->offsetExists('xbt.tracker.service')) {
                    /** @var \Harment\XBTTracker\Service\Tracker $trackerService */
                    $trackerService = \XF::app()->get('xbt.tracker.service');
                    if (method_exists($trackerService, 'getTrackerStatus')) {
                        $status = $trackerService->getTrackerStatus() ? 'online' : 'offline';
                    }
                } else {
                    // Fallback to manual check
                    $announceUrl = \XF::options()->xbtTrackerAnnounceURL;
                    
                    if ($announceUrl) {
                        $parsedUrl = parse_url($announceUrl);
                        if (isset($parsedUrl['host'])) {
                            $host = $parsedUrl['host'];
                            $port = isset($parsedUrl['port']) ? $parsedUrl['port'] : 80;
                            $connection = @fsockopen($host, $port, $errno, $errstr, 2);
                            if ($connection) {
                                $status = 'online';
                                fclose($connection);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \XF::logException($e);
                // Keep status as offline in case of error
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
     * @return string Admin options template name
     */
    public function getOptionsTemplate()
    {
        return $this->renderer('admin:xbt_widget_tracker_stats_options', [
            'options' => $this->options
        ]);
    }
}