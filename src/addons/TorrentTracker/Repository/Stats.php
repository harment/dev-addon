<?php
namespace TorrentTracker\Repository;
use XF\Mvc\Entity\Repository;

class Stats extends Repository 
{
	private $_stats;

	public function getTopUsers($subType)
	{
		$this->_stats = \XF::app()->simpleCache()->TorrentTracker->torrentTopStats;

		$description = '';

		switch ($subType)
		{
			case 'bsharers':
				$title = \XF::phrase('top_10_best_sharers');
				$items = $this->_getTopBestSharers();
				$description = \XF::phrase('with_minimum_1gb_downloaded');
				break;
			case 'wsharers':
				$title = \XF::phrase('top_10_worst_sharers');
				$items =$this->_getTopWorstSharers();
				$description = \XF::phrase('with_minimum_1gb_downloaded');
				break;
			case 'snatchers':
				$title = \XF::phrase('top_10_snatchers');
				$items = $this->_getTopSnatchers();
				break;
			case 'downloaders':
				$title = \XF::phrase('top_10_downloaders');
				$items = $this->_getTopDownloaders();
				break;
			case 'uploaders':
			default:
				$title = \XF::phrase('top_10_uploaders');
				$items = $this->_getTopUploaders();
		}

		return array($title, $description, $items);
	}

	public function getTopTorrents($subType)
	{
		$this->_stats = \XF::app()->simpleCache()->TorrentTracker->torrentTopStats;

		$description = '';

		switch ($subType)
		{
			case 'downloaded':
				$title = \XF::phrase('top_10_most_downloaded_torrents');
				$items = $this->_getMostDownloaded();
				break;
			case 'completed':
				$title = \XF::phrase('top_10_most_completed_torrents');
				$items = $this->_getMostCompleted();
				break;
			case 'seeded':
				$title = \XF::phrase('top_10_best_seeded_torrents');
				$items = $this->_getBestSeeded();
				$description = \XF::phrase('with_minimum_5_seeders');
				break;
			case 'active':
			default:
				$title = \XF::phrase('top_10_most_active_torrents');
				$items = $this->_getMostActive();
		}

		return array($title, $description, $items);
	}

	protected function _getMostActive()
	{
		if (empty($this->_stats['mostActiveTorrents']))
		{
			return array();
		}

		$torrents = $this->_getTorrents(array_keys($this->_stats['mostActiveTorrents']));
		foreach ($torrents AS $torrentId => &$torrent)
		{
			$torrent['item_count'] = $this->_stats['mostActiveTorrents'][$torrentId];
			if ($torrent['item_count'] == 0) {  // sanity check
				unset($torrents[$torrentId]);
			}
		}

		usort($torrents, array($this, "_compare"));
		return $torrents;	
	}

	protected function _getMostDownloaded()
	{
		if (empty($this->_stats['mostDownloadedTorrents']))
		{
			return array();
		}

		$torrents = $this->_getTorrents(array_keys($this->_stats['mostDownloadedTorrents']));
		foreach ($torrents AS $torrentId => &$torrent)
		{
			$torrent['item_count'] = $this->_stats['mostDownloadedTorrents'][$torrentId];
			if ($torrent['item_count'] == 0) {  // sanity check
				unset($torrents[$torrentId]);
			}
		}

		usort($torrents, array($this, "_compare"));
		return $torrents;	
	}

	protected function _getBestSeeded()
	{
		if (empty($this->_stats['bestSeededTorrents']))
		{
			return array();
		}

		$torrents = $this->_getTorrents(array_keys($this->_stats['bestSeededTorrents']));
		foreach ($torrents AS $torrentId => &$torrent)
		{
			$torrent['item_count'] = $this->_stats['bestSeededTorrents'][$torrentId];
		}

		usort($torrents, array($this, "_compare"));
		return $torrents;	
	}

	protected function _getMostCompleted()
	{
		if (empty($this->_stats['mostDownloadedTorrents']))
		{
			return array();
		}

		$torrents = $this->_getTorrents($this->_stats['mostCompletedTorrents'], 'completed', 'desc');
		foreach ($torrents AS $torrentId => &$torrent)
		{
			$torrent['item_count'] = $torrent['completed'];
			if ($torrent['item_count'] == 0) {  // sanity check
				unset($torrents[$torrentId]);
			}
		}

		return $torrents;	
	}

	protected function _getTopUploaders()
	{
        if (empty($this->_stats['topUploaders']))
        {
            return array();
        }

		$users = $this->_getUsers($this->_stats['topUploaders'], 'uploaded', 'desc');
		foreach ($users AS $userId => &$user)
		{
			$temp = explode(' ', \XF::language()->fileSizeFormat($user['uploaded']));

			$user['item_count'] = $temp[0];
			$user['item_title'] = $temp[1];

			if ($user['uploaded'] == 99) {  // sanity check
				unset($users[$userId]);
			}
		}

		return $users;
	}

	protected function _getTopDownloaders()
	{
		if (empty($this->_stats['topDownloaders']))
		{
			return array();
		}

		$users = $this->_getUsers($this->_stats['topDownloaders'], 'downloaded', 'desc');
		foreach ($users AS $userId => &$user)
		{
			$temp = explode(' ', \XF::language()->fileSizeFormat($user['downloaded']));

			$user['item_count'] = $temp[0];
			$user['item_title'] = $temp[1];

			if ($user['downloaded'] == 0) {  // sanity check
				unset($users[$userId]);
			}
		}

		return $users;
	}

	protected function _getTopSnatchers()
	{
		if (empty($this->_stats['topSnatchers']))
		{
			return array();
		}

		$users = $this->_getUsers(array_keys($this->_stats['topSnatchers']));
		foreach ($users AS $userId => &$user)
		{
			$user['item_count'] = $this->_stats['topSnatchers'][$userId];
		}

		usort($users, array($this, "_compare"));
		return $users;
	}

	protected function _getTopBestSharers()
	{
		if (empty($this->_stats['topBestSharers']))
		{
			return array();
		}

		$users = $this->_getUsers(array_keys($this->_stats['topBestSharers']));
		foreach ($users AS $userId => &$user)
		{
			$user['item_count'] = $this->_stats['topBestSharers'][$userId];
		}

		usort($users, array($this, "_compare"));
		return $users;
	}

	protected function _getTopWorstSharers()
	{
		if (empty($this->_stats['topWorstSharers']))
		{
			return array();
		}

		$users = $this->_getUsers(array_keys($this->_stats['topWorstSharers']));
		foreach ($users AS $userId => &$user)
		{
			$user['item_count'] = $this->_stats['topWorstSharers'][$userId];
			if ($user['item_count'] >= 1)
			{
				unset($users[$userId]); // User with ratio >= 1 should not be in this list.
			}
		}

		usort($users, array($this, "_compare"));
		return $users;
	}

	protected function _getUsers($userIds, $order = '', $direction = 'desc')
	{
		if (empty($userIds))
		{
			return array();
		}

		return $this->db()->fetchAllKeyed("
			SELECT user.*, user_profile.*
				FROM xf_user AS user
			LEFT JOIN xf_user_profile AS user_profile ON (user.user_id = user_profile.user_id)
			WHERE user.user_id IN (" . $this->db()->quote($userIds) . ")
			" . ($order ? "ORDER BY user.$order $direction" : '') . "
		", 'user_id');
	}

	protected function _getTorrents($torrentIds, $order = '', $direction = 'desc')
	{
		if (empty($torrentIds))
		{
			return array();
		}

		return $this->db()->fetchAllKeyed("
			SELECT torrent.*, thread.title, IF(user.username IS NULL, thread.username, user.username) AS username, 
				thread.user_id AS tuser_id, user.avatar_date, user.avatar_width, user.avatar_height, user.gravatar
			FROM xftt_torrent AS torrent
			INNER JOIN xf_thread AS thread ON (thread.thread_id = torrent.thread_id)
			LEFT JOIN xf_user AS user ON (user.user_id = torrent.user_id)
			WHERE torrent.torrent_id IN (" . $this->db()->quote($torrentIds) . ")
			" . ($order ? "ORDER BY torrent.$order $direction" : '') . "
		", 'torrent_id');
	}

	protected function _compare($a, $b)
	{
		return ($a['item_count'] < $b['item_count'] ? 1 : -1);
	} 
}