<?php
namespace TorrentTracker\Repository;
use XF\Mvc\Entity\Repository;

class Tracker extends Repository
{
    public function updateTrackerOptions($torrentOption, $anonymousAnnounce, $freeleech, $cloudfare)
    {
        $anonymousAnnounce = ($anonymousAnnounce ? 0 : 1);
        $cloudfare = ($cloudfare ? 1 : 0);

        $this->db()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'anonymous_announce\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $anonymousAnnounce);

        $this->db()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'listen_port\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $torrentOption);

        $this->db()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'freeleech\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $freeleech);

        $this->db()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'cloudfare\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $cloudfare);

        $this->buildConfig();
    }

    public function updateAdvancedTrackerOptions($announceInterval, $seedbonusInterval, $seedbonusAmount)
    {
        $this->db()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'announce_interval\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $announceInterval);

        $this->db()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'seedbonus_interval\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $seedbonusInterval);

        $this->db()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'seedbonus\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $seedbonusAmount);
    }

    public function resetGlobalMultiplier()
    {
        $this->db()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'global_multiplier\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', 0);
    }

    public function updateGlobalMultiplier($globalMultiplier, $upload_multiplier, $download_multiplier)
    {
        $globalMultiplier = ($globalMultiplier ? 1 : 0);

        $this->db()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'global_multiplier\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $globalMultiplier);

        $this->db()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'upload_multiplier\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $upload_multiplier);

        $this->db()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'download_multiplier\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $download_multiplier);

        $this->buildConfig();

    }

    public function buildConfig()
    {
        $config = $this->db()->fetchPairs('
			SELECT name, value FROM xftt_config
		');
        return $config;
    }

    public function getConfig()
    {
        $config = $this->buildConfig();
        return $config;
    }

    public function getAllBonusPoints()
    {
        return $this->db()->fetchAllKeyed('
			SELECT * FROM xftt_bonus_points 
			ORDER BY display_order ASC 
		', 'data_receivable');
    }

    public function addBonusPoint($dataReceivable, $pointsNeeded, $displayOrder)
    {
        $this->db()->insert('xftt_bonus_points', array(
            'data_receivable' => $dataReceivable,
            'points_needed' => $pointsNeeded,
            'display_order' => $displayOrder
        ));
    }

    public function removeBonusPoints($id)
    {
        $this->db()->query('
			DELETE FROM xftt_bonus_points WHERE id = ?
		', $id);
    }

	public function getAllBanClients()
	{
		return $this->db()->fetchAllKeyed('
			SELECT * FROM xftt_deny_from_clients
		', 'peer_id');
	}

	public function banClient($peerId, $comment = '')
	{
		$this->db()->insert('xftt_deny_from_clients', array(
			'peer_id'	=> $peerId,
			'comment' => $comment
		));
	}

	public function removeBanClient($peerId)
	{
		$this->db()->query('
			DELETE FROM xftt_deny_from_clients WHERE peer_id = ?
		', $peerId);
	}

	public function getAllBanIps()
	{
		return $this->db()->fetchAllKeyed('
			SELECT * FROM xftt_deny_from_hosts
		', '');
	}

	public function banIp($begin, $end, $comment = '')
	{
		$begin = ip2long($begin);
		$end = ip2long($end);
		$comment = trim($comment);

		if ($begin !== false && $end)
		{
			$this->db()->insert('xftt_deny_from_hosts', array(
				'begin'	=> $begin,
				'end'	=> $end,
				'comment' => $comment
			));

			return true;
		}
		else
		{
			throw new \LogicException("Invalid Ip Address");
		}
	}

	public function removeBanIp($begin, $end)
	{
		$this->db()->query('
			DELETE FROM xftt_deny_from_hosts WHERE begin = ? AND end = ?
		', array($begin, $end));
	}
}