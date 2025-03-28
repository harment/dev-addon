<?php
namespace TorrentTracker\Repository;
use XF\Mvc\Entity\Repository;

class FreeleechRequest extends Repository 
{
	public function countRequests($open = 1)
	{
		return $this->db()->fetchOne('
			SELECT COUNT(*) 
			FROM xftt_freeleech_request
			WHERE open = ?
		', $open);
	}

	public function getRequestById($requestId)
	{
		return $this->db()->fetchRow('
			SELECT * FROM xftt_freeleech_request 
			WHERE request_id = ?
		', 'request_id', $requestId);
	}

	public function getRequestByTorrentId($torrentId)
	{
		return $this->db()->fetchRow('
			SELECT * FROM xftt_freeleech_request 
			WHERE torrent_id = ?
		', 'torrent_id', $torrentId);
	}

	public function insertRequest($torrentId,$userid)
	{
		return $this->db()->insert('xftt_freeleech_request', array(
			'torrent_id'	=>	$torrentId,
			'user_id'	=> $userid,
			'date'	=> \XF::$time
		));
	}

	public function updateRequest($requestId, $action = '')
	{
		return $this->db()->update('xftt_freeleech_request', array(
			'action' => $action,
			'open' => ($action == '') ? 1 : 0
		), 'request_id = ' . $requestId);
	}

	public function deleteRequest($torrentId)
	{
		return $this->db()->delete('xftt_freeleech_request', 'request_id = ' . $requestId);
	}

	public function getRequests($open = 1, array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT request.*, user.*, torrent.size, torrent.freeleech, IF(user.username IS NULL, thread.username, user.username) AS username,
				thread.title, thread.thread_id, thread.node_id, node.title as nodetitle
				FROM xftt_freeleech_request AS request
				INNER JOIN xftt_torrent AS torrent ON (torrent.torrent_id = request.torrent_id)
				INNER JOIN xf_thread AS thread ON (thread.thread_id = torrent.thread_id)
				LEFT JOIN xf_user AS user ON (user.user_id = thread.user_id)
				LEFT JOIN xf_node AS node ON (node.node_id = thread.node_id)
				WHERE request.open = ' . $this->db()->quote($open) . '
				ORDER BY request.date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'request_id');
	} 
}