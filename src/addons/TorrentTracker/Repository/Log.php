<?php
namespace TorrentTracker\Repository;
use XF\Mvc\Entity\Repository;

class Log extends Repository
{
	public function getLogById($id)
	{
		return $this->db()->fetchRow('
			SELECT * FROM xftt_log
			WHERE log_id = ?
		', $id);
	}

	public function deleteLog($id)
	{
		$db = $this->db();
		$db->delete('xftt_log', 'log_id = ' . $db->quote($id));
	}

	public function clearLog()
	{
		$this->db()->query('TRUNCATE TABLE xftt_log');
	}

	public function prepareLogEntries($entries)
	{
		if (empty($entries))
		{
			return;
		}
		
		foreach ($entries AS &$entry)
		{
			$entry = $this->prepareLogEntry($entry);
		}

		return $entries;
	}

	public function prepareLogEntry($entry)
	{
		$entry['params'] = unserialize($entry['params']);
		$entry['action'] =  \XF::phrase('xtt_' . $entry['action']);

		if ($entry['is_error'])
		{
			$entry['errorClass'] = 'alert';
		}

		return $entry;
	}

	public function countLogEntries()
	{
		return $this->db()->fetchOne('
			SELECT COUNT(*)
			FROM xftt_log
		');
	}

	public function getLogEntries($limit, $offset)
	{
		return $this->db()->fetchAllKeyed($this->db()->limit(
			'
				SELECT * FROM xftt_log
				ORDER BY log_date DESC
			', $limit, $offset
		), 'log_id');
	}
}