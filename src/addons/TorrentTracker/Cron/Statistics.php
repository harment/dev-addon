<?php

namespace TorrentTracker\Cron;
use TorrentTracker\Inc\Tracker;

class Statistics
{
	public static function cacheTorrentStatistics()
	{
		$config = \XF::repository('TorrentTracker:Tracker')->getConfig();
		$cache = \XF::app()->simpleCache()->TorrentTracker;
		$db = \XF::db();
		$db->update('xftt_peer', array(
			'active' => 0
		), 'mtime < ' . (\XF::$time - (1.5 * $config['announce_interval'])));

		$stats = array();
		$result = Tracker::send(array(
			'action' => 'stats'
		)); 

		if (is_array($result) && !empty($result))
		{
			$stats = $result;
			$stats['peers'] = array(
				'seeding' => $stats['seeders'],
				'leeching' => $stats['leechers']
			);				

			$stats['online'] = true;
			$torrents = $db->fetchOne("
				SELECT COUNT(torrent_id)
				FROM xftt_torrent
			");
			$stats['torrents'] = $torrents;

		} 


		if (empty($stats))
		{

			$snatches = $db->fetchOne("
				SELECT COUNT(user_id)
				FROM xftt_snatched
			");

			$peers = $db->fetchPairs("
				SELECT IF(`left` = 0, 'seeding', 'leeching') AS type, COUNT(user_id) AS total
				FROM xftt_peer
				WHERE active = 1
				GROUP BY type
			");

			$torrents = $db->fetchOne("
				SELECT COUNT(torrent_id)
				FROM xftt_torrent
			");
			$stats = array(
				'snatches'	=> $snatches,
				'peers'		=> $peers,
				'torrents'	=> $torrents,
				'online'	=> false
			);
		}

		$cache->statisticsCache = $stats;
	}

	public static function updateDaily()
	{
		$db = \XF::db();

		$config = \XF::repository('TorrentTracker:Tracker')->getConfig();
		if (isset($config['log_announce']) && $config['log_announce']) 
		{
			$db->query('TRUNCATE TABLE xftt_announce_log');
		}
		
		if (isset($config['log_scrape']) && $config['log_scrape']) 
		{
			$db->query('TRUNCATE TABLE xftt_scrape_log');
		}

		$db->query('TRUNCATE TABLE xftt_request_reseed');

		// Delete dead peers (last announce time older than a week)
		// $db->delete('xftt_peer', 'mtime < ' . (\XF::$time - 604800));

		// Delete logs older than a week
		$db->delete('xftt_log', 'log_date < ' . (\XF::$time - 604800));

		// Delete freeleech requests older than a week
		$db->delete('xftt_freeleech_request', 'date < ' . (\XF::$time - 604800));
	}

	public static function updateWeekly()
	{
		$cache = \XF::app()->simpleCache()->TorrentTracker;
		$db = \XF::db();

		$topStats = array();

		// MOST DOWNLOADED TORRENTS
		$topStats['mostDownloadedTorrents'] = $db->fetchPairs('
			SELECT attachment_id, view_count FROM xf_attachment AS a
			INNER JOIN xftt_torrent AS t ON (t.torrent_id = a.attachment_id)
			ORDER BY view_count DESC
			LIMIT 10
		');

		// MOST COMPLETED TORRENTS
		$topStats['mostCompletedTorrents'] = $db->fetchAllColumn('
			SELECT torrent_id FROM xftt_torrent
			ORDER BY completed DESC
			LIMIT 10
		');

		// MOST ACTIVE TORRENTS
		$topStats['mostActiveTorrents'] = $db->fetchPairs('
			SELECT torrent_id, (seeders + leechers) as peers FROM xftt_torrent
			ORDER BY peers DESC
			LIMIT 10
		');

		// BEST SEEDED TORRENTS
		$topStats['bestSeededTorrents'] = $db->fetchPairs('
			SELECT torrent_id, seeders / leechers as ratio FROM xftt_torrent
			WHERE seeders > 5 AND leechers > 0
			ORDER BY ratio DESC
			LIMIT 10
		');

		// TOP DOWNLOADERS
		$topStats['topDownloaders'] = $db->fetchAllColumn('
			SELECT user_id FROM xf_user
			ORDER BY downloaded DESC
			LIMIT 10
		');

		// TOP UPLOADERS
		$topStats['topUploaders'] = $db->fetchAllColumn('
			SELECT user_id FROM xf_user
			ORDER BY uploaded DESC
			LIMIT 10
		');

		// TOP BEST SHARERS
		$topStats['topBestSharers'] = $db->fetchPairs('
			SELECT user_id, (uploaded / downloaded) AS ratio FROM xf_user
			WHERE downloaded > 1073741824
			ORDER BY ratio DESC
			LIMIT 10
		');

		// TOP WORST SHARERS
		$topStats['topWorstSharers'] = $db->fetchPairs('
			SELECT user_id, (uploaded / downloaded) AS ratio FROM xf_user
			WHERE downloaded > 1073741824
			ORDER BY ratio ASC
			LIMIT 10
		');

		// TOP SNATCHERS
		$topStats['topSnatchers'] = $db->fetchPairs('
			SELECT user_id, COUNT(user_id) AS snatches FROM xftt_snatched
			GROUP BY user_id
			ORDER BY snatches DESC
			LIMIT 10
		');

		$cache->torrentTopStats = $topStats;
	}	
}