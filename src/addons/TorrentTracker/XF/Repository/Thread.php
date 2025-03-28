<?php

namespace TorrentTracker\XF\Repository;

class Thread extends XFCP_Thread
{
	public function findThreadsForForumView(\XF\Entity\Forum $forum, array $limits = [])
	{
		$parent = parent::findThreadsForForumView($forum, $limits);
		if($forum->Node->is_torrent_category)
		{ 
		$parent->with('Torrent');
			 
			return $parent;
		}
		else
		{
			return $parent;
		}
	}

	public function findUploadMultiplier(\XF\Entity\Forum $forum)
	{
		return $forum->upload_multiplier;
	}

	public function findDownloadMultiplier(\XF\Entity\Forum $forum)
	{
		return $forum->download_multiplier;
	}
}