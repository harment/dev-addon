<?php

namespace TorrentTracker\Widget;
use XF\Widget\AbstractWidget;

class TorrentStats extends AbstractWidget
{
	public function render()
	{
		$cache =\XF::app()->simpleCache()->TorrentTracker->statisticsCache;
		
		$viewParams = [
			'Statistics' => $cache,
		];

		return $this->renderer('widget_xentorrent_sidebar_tracker_stats', $viewParams);
	}

	public function getOptionsTemplate()
	{
		return null;
	}
}