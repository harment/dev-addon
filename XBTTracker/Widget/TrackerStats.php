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
        return [];
    }
    
    /**
     * Render the widget
     *
     * @return string|array
     */
    public function render()
    {
        /** @var \XBTTracker\Repository\Torrent $torrentRepo */
        $torrentRepo = $this->repository('XBTTracker:Torrent');
        
        $trackerStats = $torrentRepo->getTorrentStats();
        
        // Get tracker status
        $status = 'offline';
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
        
        $viewParams = [
            'trackerStats' => $trackerStats,
            'trackerStatus' => $status,
            'style' => $this->style
        ];
        
        return $this->renderer('xbt_widget_tracker_stats', $viewParams);
    }
}
