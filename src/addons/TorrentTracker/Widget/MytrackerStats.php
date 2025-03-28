<?php

namespace TorrentTracker\Widget;
use XF\Widget\AbstractWidget;

class MytrackerStats extends AbstractWidget
{
	public function render()
	{
		$visitor = \XF::visitor();

		$up = $this->finder('TorrentTracker:Peer')->where('user_id', $visitor->user_id)->where('active', 1)->where('left' ,0)->total();
		$down = $this->finder('TorrentTracker:Peer')->where('user_id', $visitor->user_id)->where('active', 1)->where('left', '!=' ,0)->total();
		$viewParams = [
			'up' => $up,
			'down' => $down
		];
		return $this->renderer('widget_xentorrent_sidebar_my_tracker_stats', $viewParams);
	}

	public function getOptionsTemplate()
	{
		return null;
	}
}