<?php

namespace TorrentManager\Widget;

use XF\Widget\AbstractWidget;

class TrackerStats extends AbstractWidget
{
    public function render()
    {
        $stats = \XF::db()->fetchRow("SELECT SUM(seeders) as seeders, SUM(leechers) as leechers, SUM(peers) as peers, SUM(snatches) as snatches FROM xf_torrent_stats");

        $viewParams = [
            'seeders' => $stats['seeders'] ?? 0,
            'leechers' => $stats['leechers'] ?? 0,
            'peers' => $stats['peers'] ?? 0,
            'snatches' => $stats['snatches'] ?? 0
        ];

        return $this->renderer('torrent_manager_widget_tracker_stats', $viewParams);
    }
}