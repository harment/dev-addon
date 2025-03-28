<?php
namespace TorrentTracker\Repository;
use XF\Mvc\Entity\Repository;
use XF\Template\Compiler\Syntax\Str;

class Torrent extends Repository
{
	const FETCH_THREAD = 0x01;
	const FETCH_USER = 0x02;
	const FETCH_FORUM = 0x04;
	const FETCH_INFO = 0x08;
	const FETCH_ATTACHMENT = 0x10;
	const FETCH_REQUEST = 0x20;

	public function getTorrentByInfoHash($infoHash)
	{
		return \XF::db()->fetchRow(/** @lang text */ '
			SELECT * FROM xftt_torrent
			WHERE info_hash = UNHEX(?)
		', $infoHash);
	}

	public function findTorrentForList(array $viewableCategoryIds = null, $visibilityLimit = true)
    {
        $visitor = \XF::visitor();

        $torrentFinder = $this->finder('TorrentTracker:Torrent');

        if($viewableCategoryIds){
            $torrentFinder->where('Thread.Forum.node_id', $viewableCategoryIds);
        }

        if($visibilityLimit){
            $torrentFinder->where('Thread.discussion_state','visible');
        }

		return $this->finder('TorrentTracker:Torrent')
            ->with('Attachment', true)
            ->with('Thread', true)
            ->with('Thread.User')
            ->with('Thread.Forum', true)
            ->with('Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id)
            ->where('Thread.discussion_type', '<>', 'redirect');
    }

    public function findPeerTorrentForList()
    {
        return $this->finder('TorrentTracker:Peer')
            ->with('Torrent', true);
    }

    public function getPeerList($torrentId)
    {
        return $this->finder('TorrentTracker:Peer')
        	->order('active','DESC')
            ->with('User')
            ->where('torrent_id', $torrentId);
    }
     public function getSnapList($torrentId)
    {
        return $this->finder('TorrentTracker:Snatched')
            ->with('User')
            ->where('torrent_id', $torrentId);
    }

    public function findTorrentFreeleechRequesetForList($open)
    {
        $finder = $this->finder('TorrentTracker:Request');
        if($open == 1)
        {
            $finder->where('open',1);
        }
        $finder->with('Torrent', true)
        		->with('User',true);
        return $finder;
    }

    public function findTorrentCatForList()
    {
        return $this->finder('XF:Node')
//            ->with('Node', true)
            ->with('Parent',false);
    }

	public function getTorrentByThreadId($threadId)
	{
		return \XF::db()->fetchRow('
			SELECT * FROM xftt_torrent
			WHERE thread_id = ?
		', $threadId);
	}

 	public function findTorrentInfo($attachmentId)
	{
		/** @var \XF\Finder\Thread $finder */
		$finder = $this->finder('TorrentTracker:Torrent');

		$finder->where('torrent_id', $attachmentId)
			->with(['Info', 'Request', 'Attachment','User']); 

		return $finder;
	}



	public function getTorrentById($torrentId, $fetchOptions = array())
	{
		$joinOptions = $this->prepareTorrentFetchOptions($fetchOptions);

		return \XF::db()->fetchRow('
			SELECT torrent.*, torrent.torrent_id as attachment_id
				' . $joinOptions['selectFields'] . '
			FROM xftt_torrent AS torrent
			' . $joinOptions['joinTables'] . '
			WHERE torrent.torrent_id = ?
		', $torrentId);
	}

	public function getTorrentsByIds($torrentIds, $fetchOptions = array())
	{
		if (empty($torrentIds))
		{
			return array();
		}

		$joinOptions = $this->prepareTorrentFetchOptions($fetchOptions);

		return $this->db()->fetchAllKeyed('
			SELECT torrent.*, torrent.torrent_id as attachment_id
				' . $joinOptions['selectFields'] . '
			FROM xftt_torrent AS torrent
			' . $joinOptions['joinTables'] . '
			WHERE torrent.torrent_id IN (' . \XF::db()->quote($torrentIds) . ')
			' . $joinOptions['orderClause'] . $joinOptions['orderDirection'] . '
		', 'torrent_id');
	}

	public function countTorrents($conditions, $fetchOptions = array())
	{
		$whereConditions = $this->prepareTorrentConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareTorrentFetchOptions($fetchOptions);

		return \XF::db()->fetchOne('
			SELECT COUNT(*)
			FROM xftt_torrent AS torrent
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereConditions . '
		');
	}

	public function countStickyTorrents()
	{
		return \XF::db()->fetchOne('
			SELECT COUNT(*) FROM xftt_torrent AS torrentCount WHERE sticky=1');
	}

	public function getTorrentIdsInRange($conditions, $fetchOptions)
	{
		$db = \XF::db();

		$whereConditions = $this->prepareTorrentConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareTorrentFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $db->fetchCol($this->db()->limit('
			SELECT torrent.torrent_id
			FROM xftt_torrent AS torrent
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereConditions . '
			' . $sqlClauses['orderClause'] . $sqlClauses['orderDirection'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		));
	}
	
	public function getTorrents($conditions, $fetchOptions)
	{
		$whereConditions = $this->prepareTorrentConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareTorrentFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->db()->fetchAllKeyed($this->db()->limit('
				SELECT torrent.*, torrent.torrent_id as attachment_id
				' . $sqlClauses['selectFields'] . '
				FROM xftt_torrent AS torrent
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereConditions . ' 
				' . $sqlClauses['orderClause'] . $sqlClauses['orderDirection'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'torrent_id');
	}

	public function getTorrentsForUserId($conditions, $fetchOptions = array())
	{
		$fetchOptions['forUserId'] = true;
		$fetchOptions['join'] = self::FETCH_THREAD |  self::FETCH_FORUM;

		if ($conditions['filter'] == 'my')
		{
			$fetchOptions['orderBy'] = 'torrent_id';
			return $this->getTorrents($conditions, $fetchOptions);
		}
		else
		{
			$whereConditions = $this->prepareTorrentConditions($conditions, $fetchOptions);
			$sqlClauses = $this->prepareTorrentFetchOptions($fetchOptions);
			$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

			return $this->db()->fetchAllKeyed($this->db()->limit('
					SELECT peer.*, torrent.torrent_id as attachment_id, torrent.thread_id, 
						torrent.size, torrent.seeders, torrent.leechers, torrent.completed AS snatched, torrent.freeleech
						' . $sqlClauses['selectFields'] . ' 
					FROM xftt_peer AS peer
					INNER JOIN xftt_torrent AS torrent ON (torrent.torrent_id = peer.torrent_id)
					' . $sqlClauses['joinTables'] . '
					WHERE ' . $whereConditions . '
					ORDER BY peer.mtime DESC
				', $limitOptions['limit'], $limitOptions['offset']
			), 'torrent_id');
		}
	}

	public function countTorrentsForUserId($conditions, $fetchOptions = array())
	{
		$fetchOptions['forUserId'] = true;

		$whereConditions = $this->prepareTorrentConditions($conditions, $fetchOptions);

		if ($conditions['filter'] == 'my')
		{
			return \XF::db()->fetchOne('
				SELECT COUNT(*) 
				FROM xftt_torrent AS torrent
				WHERE ' .  $whereConditions . '
			');
		}
		else
		{
			return \XF::db()->fetchOne('
				SELECT COUNT(*)
				FROM xftt_peer AS peer 
				INNER JOIN xftt_torrent AS torrent ON (torrent.torrent_id = peer.torrent_id)
				WHERE ' .  $whereConditions . '
			');
		}
	}

	public function prepareTorrentConditions($conditions, $fetchOptions)
	{
		$sqlConditions = array();
		$db = \XF::db();

		if (isset($fetchOptions['forUserId'])) 
		{
			if (isset($conditions['userId']))
			{
				$sqlConditions[] = 'peer.user_id = ' . $db->quote($conditions['userId']);
			}

			switch($conditions['filter'])
			{
				case 'seed':
					$sqlConditions[] = 'peer.active = 1 AND peer.left = 0';
					break;
				case 'leech':
					$sqlConditions[] = 'peer.active = 1 AND peer.left > 0';
					break;
				case 'inactive':
					$sqlConditions[] = 'peer.active = 0';
					break;
				case 'my':
				default:
					$sqlConditions = array('torrent.user_id = ' . $db->quote($conditions['userId']));
			}

			return $this->getConditionsForClause($sqlConditions);
		}

		if (isset($conditions['category_id']))
		{
			if (is_array($conditions['category_id']))
			{
				if (!$conditions['category_id'])
				{
					$sqlConditions[] = 'torrent.category_id IS NULL';
				}
				else
				{
					$sqlConditions[] = 'torrent.category_id IN (' . $db->quote($conditions['category_id']) . ')';
				}
			}
			else
			{
				$sqlConditions[] = 'torrent.category_id = ' . $db->quote($conditions['category_id']);
			}			
		}

		if (isset($conditions['thread_id']))
		{
			if (is_array($conditions['thread_id']))
			{
				if (!$conditions['thread_id'])
				{
					$sqlConditions[] = 'torrent.thread_id IS NULL';
				}
				else
				{
					$sqlConditions[] = 'torrent.thread_id IN (' . $db->quote($conditions['thread_id']) . ')';
				}
			}
			else
			{
				$sqlConditions[] = 'torrent.thread_id = ' . $db->quote($conditions['thread_id']);
			}			
		}

		if (!empty($conditions['freeleech']))
		{
			$sqlConditions[] = 'torrent.freeleech = 1';
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function prepareTorrentFetchOptions($fetchOptions) 
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
		$orderDirection = '';

		if (!empty($fetchOptions['join']))
		{		
			if ($fetchOptions['join'] & self::FETCH_INFO)
			{
				$selectFields .= ', torrent_info.file_details AS other_details';
				$joinTables .= '
					LEFT JOIN xftt_torrent_info AS torrent_info ON
						(torrent_info.torrent_id = torrent.torrent_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_ATTACHMENT)
			{
				$selectFields .= ', attachment.*';
				$joinTables .= '
					LEFT JOIN xf_attachment AS attachment ON
						(attachment.attachment_id = torrent.torrent_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_REQUEST)
			{
				$selectFields .= ', request.request_id, request.action as request_status';
				$joinTables .= '
					LEFT JOIN xftt_freeleech_request AS request ON
						(request.torrent_id = torrent.torrent_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					user.user_id, user.email, user.user_group_id,
					user.torrent_pass_version, 
					user.downloaded,
					user.uploaded,
					user.can_leech,
					user.wait_time,
					user.peers_limit,
					user.torrent_pass,
					user.seedbonus,
					IF(user.username IS NULL, thread.username, user.username) AS username,
					user.freeleech AS user_freeleech
				';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = torrent.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_FORUM)
			{
				$selectFields .= ',
					node.title as node_title, node.is_torrent_category, forum.torrent_category_image';
				$joinTables .= '
					LEFT JOIN xf_node as node ON (node.node_id = torrent.category_id)
					LEFT JOIN xf_forum as forum ON (forum.node_id = node.node_id)
				';
			}

			if (($fetchOptions['join'] & self::FETCH_THREAD))
			{
				$selectFields .= ',
					thread.title, thread.node_id, thread.reply_count, thread.prefix_id, thread.username, thread.user_id AS tuser_id';
				$joinTables .= '
					LEFT JOIN xf_thread AS thread ON
						(thread.thread_id = torrent.thread_id)';
			}
		}

		if (isset($fetchOptions['orderBy']))
		{
			switch ($fetchOptions['orderBy'])
			{
				case 'seeders':
					$orderBy = 'torrent.seeders';
					break;
				case 'snatched':
					$orderBy = 'torrent.completed';
					break;
				case 'size':
					$orderBy = 'torrent.size';
					break;
				case 'replies':
					$orderBy = 'thread.reply_count';
					break;
				case 'time':
				default:
					$orderBy = 'torrent.ctime';
			}

			$orderDirection = isset($fetchOptions['orderDirection']) ? $fetchOptions['orderDirection'] : 'desc';
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables,
			'orderClause'  => ($orderBy ? "ORDER BY $orderBy " : ''),
			'orderDirection' => $orderDirection
		);
	}

	public function deleteTorrents($torrentIds)
	{
		if (empty($torrentIds))
		{
			return;
		}

		if (!is_array($torrentIds))
		{
			$torrentIds = array($torrentIds);
		}

		$db = \XF::db();
		
		$torrents = $db->fetchPairs('
			SELECT torrent_id, thread_id
			FROM xftt_torrent
			WHERE torrent_id IN (' . $db->quote($torrentIds) . ')'
		);

		if (!empty($torrents))
		{
			$torrentIds = array_keys($torrents);
			$db->update('xftt_torrent', array(
				'flags' => 1
			), 'torrent_id IN (' . $db->quote($torrentIds) . ')');

			$db->delete('xftt_torrent_info',
				'torrent_id IN (' . $db->quote($torrentIds) . ')'
			);
			
			$db->delete('xftt_peer',
				'torrent_id IN (' . $db->quote($torrentIds) . ')'
			);

			$db->update('xftt_peer', array(
				'active' => 0
			), 'torrent_id IN (' . $db->quote($torrentIds) . ')');

		}
	}

	public function deleteTorrent($torrentId)
	{		
		$torrent = $this->getTorrentById($torrentId);
		if (!empty($torrent))		
		{
			$db = \XF::db();

//			Set Torrent for deletion by xbt through flags=1
			$db->update('xftt_torrent', array(
				'flags' => 1
			), 'torrent_id = ' . $db->quote($torrentId));

			$db->delete('xftt_torrent_info',
				'torrent_id = ' . $db->quote($torrentId)
			);
		}
	}

	public function prepareTorrents($torrents = array())
	{
		if (empty($torrents)) 
		{
			return array();
		}

		foreach($torrents AS $torrentId => &$torrent) {
			$torrent = $this->prepareTorrent($torrent);
		}

		return $torrents;
	}

	public function prepareTorrent($torrent)
	{
		if (isset($torrent['other_details']))
		{
			$torrent['other_details'] = unserialize($torrent['other_details']);
		}

		if (empty($torrent['title']) && isset($torrent['other_details']['name']))
		{
			$torrent['title'] = XenForo_Helper_String::censorString($torrent['other_details']['name']);
		}

		$torrent['anonymous'] = (isset($torrent['tuser_id']) && $torrent['tuser_id'] == 0) ? true : false;

		if (\XF::options()->xenTorrentMagnetLink)
		{
			if (empty($torrent['title'])) 
			{
				$torrent['title'] = '';
			}

			$torrent['magnetLink'] = $this->getMagnetLink($torrent['info_hash'], $torrent['title'], $torrent['size']);
		}

		return $torrent;
	}

	public function filterUnviewableTorrents($torrents)
	{
		if (empty($torrents)) 
		{
			return array();
		}

		foreach ($torrents AS $torrentId => $torrent)
		{
			if ($torrent['thread_id'] == 0 || $torrent['flags'] == 1)
			{
				unset($torrents[$torrentId]);
			}
		}

		return $torrents;
	} 

	public function getSnatchers($torrentId)
    {
        return $this->finder('TorrentTracker:Snatched')
            ->with('User')
            ->with('Torrent')
            ->where('torrent_id', $torrentId);
    }

    /**
     * @throws \Exception
     */
    public function downloadTorrent($attachment, $attachmentFile, $torrentFile)
    {
		$this->_canDownloadTorrent($attachment,$torrentFile);
		$infoHash = unpack('H*', $torrentFile->info_hash);
		$infoHash = reset($infoHash);
		$tempFile = \XF\Util\File::copyAbstractedPathToTempFile($attachmentFile);
		$torrent = \TorrentTracker\Torrent::createFromTorrentFile($tempFile);

		if(\XF::options()->xenAdditionalTorrentTracker['xenAdditionalTrackerEnabled'])
		{
			$torrent->setAnnounceWithAdditional($this->getAnnounceUrl($infoHash),$this->getAdditionalAnnounceUrl($infoHash)); 
		}else{
			$torrent->setAnnounce($this->getAnnounceUrl($infoHash)); 
		}
		

		$commentdata = \XF::options()->xenTorrentComment;
		
		if ($commentdata) 
		{
			$comment = substr($commentdata, 0, 100);
			$torrent->setComment($comment);
		}

		if (\XF::options()->xenTorrentPrivateFlag)
		{
			$torrent->setPrivate();
		}
		
		$torrent->save($tempFile);

		return $tempFile;

	}

	public function getAnnounceUrl($infoHash = null)
	{
		$tracker = \XF::options()->xenTorrentTracker;

		$host = $tracker['host'];
		if (empty($host))
		{
			throw new \Exception(\XF::phrase('torrent_tracker_host_is_not_set'));
		}

		$port = $tracker['port'];
		if (empty($port))
		{
			throw new \Exception(\XF::phrase('torrent_tracker_port_is_not_set')); 
		}

		$config = $this->getTrackerRepo()->getConfig();

		if (!empty($config['anonymous_announce']) || $infoHash === null)
		{
			if(\XF::options()->xenTorrentHttpsTracker)
			{
				return "https://$host:$port/announce";
			}else{
				return "http://$host:$port/announce";
			}
			
		}
		else
		{
			$torrentPass = $this->_generateTorrentPass($infoHash, $config['torrent_pass_private_key']);

//			if(\XF::options()->xenTorrentHttpsTracker)
//			{
//				return "https://$host:$port/$torrentPass/announce";
//			}else{
//				return "http://$host:$port/$torrentPass/announce";
//			}
            if(\XF::options()->xfdevTrackerWithoutPort){
                if(\XF::options()->xenTorrentHttpsTracker)
                {
                    return "https://$host/$torrentPass/announce";
                }else{
                    return "http://$host/$torrentPass/announce";
                }
            }else{
                if(\XF::options()->xenTorrentHttpsTracker)
                {
                    return  "https://$host:$port/$torrentPass/announce";
                }else{
                    return "http://$host:$port/$torrentPass/announce";
                }
            }
		}
	}

	public function getAdditionalAnnounceUrl($infoHash = null)
	{
		$additionalTrackerHost = \XF::options()->xenAdditionalTorrentTracker['host'];
		$additionalTrackerPort = \XF::options()->xenAdditionalTorrentTracker['port'];

		if (empty($additionalTrackerHost))
		{
			throw new \Exception(\XF::phrase('torrent_tracker_host_is_not_set'));
		}

		if (empty($additionalTrackerPort))
		{
			throw new \Exception(\XF::phrase('torrent_tracker_port_is_not_set')); 
		}

		
		$config = $this->getTrackerRepo()->getConfig();

		if (!empty($config['anonymous_announce']) || $infoHash === null)
		{
			if(\XF::options()->xenTorrentHttpsTracker)
			{
				return "https://$additionalTrackerHost:$additionalTrackerPort/announce";
			}else{
				return "http://$additionalTrackerHost:$additionalTrackerPort/announce";
			}
		}
		else
		{
			$torrentPass = $this->_generateTorrentPass($infoHash, $config['torrent_pass_private_key']);	

			if(\XF::options()->xenTorrentHttpsTracker)
			{
				return "https://$additionalTrackerHost:$additionalTrackerPort/$torrentPass/announce";
			}else{
				return "http://$additionalTrackerHost:$additionalTrackerPort/$torrentPass/announce";
			}
		}
	}

	public function getMagnetLink($infoHash, $name, $size) 
	{
		try 
		{
			$infoHash = unpack('H*', $infoHash);
			$infoHash = reset($infoHash);

			$announceUrl = $this->getAnnounceUrl($infoHash);			
		}
		catch (Exception $e) 
		{
			return false;
		}

		$params = array(
			"xl=$size",
			"xt=urn:btih:$infoHash",
			"tr=$announceUrl",
			"dn=$name"
		);

		return ("magnet:?" . implode('&', $params));
	}	

	public function getSortFields()
	{
		return array(
			'time',
			'seeders',
			'size',
			'leechers',
			'snatched',
			'replies',
		);
	}

	public function getReseedRequestByUserId($userId)
	{
		return \XF::db()->fetchOne('
			SELECT COUNT(*) AS total
			FROM xftt_request_reseed
			WHERE user_id = ?
		', $userId);
	}

	public function insertReseedRequest($userId, $torrentId)
	{
		return \XF::db()->insert('xftt_request_reseed', array(
			'torrent_id' => $torrentId,
			'user_id' => $userId
		));
	}

	protected function _canDownloadTorrent($torrent,$torrentInfo)
	{
        $options = \XF::options();
        $user = \XF::visitor();

        $torrent = $this->getTorrentById($torrent['attachment_id']);

        if ($torrent['user_id'] == $user['user_id'] && $user->hasPermission('xenTorrentTracker','canDownloadOwnTorrents'))
        {
            return true;
        }

		if (!$this->canDownloadTorrent())
		{
			throw new \Exception(\XF::phrase('do_not_have_permission_to_download_torrent'));
		}

		if(!$this->canDownloadTorrentForNode($torrentInfo->Thread->node_id))
        {
            throw new \Exception(\XF::phrase('do_not_have_permission_to_download_torrent'));
        }

		if(!$user->can_leech)
        {
            throw new \Exception(\XF::phrase('your_downloading_rights_are_disabled'));
        }

		if (!$torrent)
		{
			throw new \Exception(\XF::phrase('invalid_torrent_file')); 
		}

        if ($torrent['user_id'] == $user['user_id'])
        {
            return true;
        }

		if($options->xfttDownloadSnatchedTorrents)
        {
            $snatchedBefore = \XF::db()->fetchRow('
			SELECT * FROM xftt_snatched 
			WHERE user_id = ? AND torrent_id = ?
		', array($user['user_id'], $torrent['torrent_id']));

            if ($snatchedBefore)
            {
                 return true;
            }
        }


		if ($options->xenTorrentMinimumRatio > 0)
		{
            if(($options->xfttDisableRatioCheck && $torrent['freeleech']) || ($options->xfttDisableRatioCheck && $options->xenTorrentGlobalFreeleech))
            {
                return true;
            }else{
                if (isset($user['ratio']) && is_numeric($user['ratio']) && $user['ratio'] < $options->xenTorrentMinimumRatio)
                {
                    throw new \Exception(\XF::phrase('minimum_ratio_required_to_download_torrent_is_x', ['ratio' => $options->xenTorrentMinimumRatio]));
                }
            }
		}

		if ($options->xenTorrentMinimumPost > 0)
		{
			if ($user['message_count'] < $options->xenTorrentMinimumPost)
			{
				throw new \Exception(\XF::phrase('minimum_post_required_to_download_torrent_is_x', ['ratio' => $options->xenTorrentMinimumPost]));  
			}
		}

		if ($options->xenTorrentMustReply)
		{
			$replied = \XF::db()->fetchRow('
				SELECT post_id
				FROM xf_post
				WHERE user_id = ? AND thread_id = ? AND message_state = \'visible\'
			', array($user['user_id'], $torrent['thread_id']));

			if (empty($replied))
			{
				throw new \Exception(\XF::phrase('you_must_reply_to_download_torrent')); 
			}
		}

		if ($options->xenTorrentMustLike)
		{
			$thread = \XF::db()->fetchRow('
				SELECT first_post_id
				FROM xf_thread
				WHERE thread_id = ?
			', $torrent['thread_id']);

			if (empty($thread))
			{
				return true;
			}

			$liked = \XF::db()->fetchRow('
				SELECT reaction_id
				FROM xf_reaction_content
				WHERE content_type = \'post\' AND reaction_user_id = ? AND content_id = ?
			', array($user['user_id'], $thread['first_post_id']));

			if (empty($liked))
			{
				throw new \Exception(\XF::phrase('you_must_add_reaction_to_download_torrent'));
			}
		}
	}

	protected function _generateTorrentPass($hashInfo, $privateKey = null, $user = null)
	{
		if ($user === null)
		{
			$user = \XF::visitor();
		}

		if ($privateKey === null)
		{
			$privateKey = \XF::db()->fetchOne("
				SELECT value FROM xftt_config WHERE name = 'torrent_pass_private_key'
			");

			if (empty($privateKey))
			{
				return;
			}
		}

		$torrentPass = sprintf('%08x%s', $user->user_id, substr(sha1(sprintf('%s %d %d %s', $privateKey, $user->torrent_pass_version, $user->user_id, pack('H*', $hashInfo))), 0, 24));

		return $torrentPass;
	}

	public function canViewTorrents($viewingUser = null)
	{

		return \XF::visitor()->hasPermission('xenTorrentTracker', 'view');
	}

	public function canDownloadTorrent($viewingUser = null)
	{

		return \XF::visitor()->hasPermission('xenTorrentTracker', 'download');
	}

	public function canDownloadTorrentForNode($nodeId)
    {
        return \XF::visitor()->hasNodePermission($nodeId,'viewAttachment');
    }

	public function canUploadTorrent($viewingUser = null)
	{

		return \XF::visitor()->hasPermission('xenTorrentTracker', 'upload');
	}

	public function canViewPeerList($viewingUser = null)
	{

		return \XF::visitor()->hasPermission('xenTorrentTracker', 'viewPeerList');
	}

	public function canViewSnatchList($viewingUser = null)
	{

		return \XF::visitor()->hasPermission('xenTorrentTracker', 'viewSnatchList');
	}

	public function canAcceptFreeleechRequest($viewingUser = null)
	{
		return \XF::visitor()->hasPermission('xenTorrentTracker', 'canMakeFreeleech');
	}

	protected function getTrackerRepo()
	{
		return $this->repository('TorrentTracker:Tracker');
	}

	public function getSeeedingTorrentsList(){
        $peerRepo = $this->finder('TorrentTracker:Peer')->where('user_id',\XF::visitor()->user_id)
            ->where('active', 1)
            ->where('left', 0);

        return $peerRepo->fetch()->pluckNamed('torrent_id');
    }
}