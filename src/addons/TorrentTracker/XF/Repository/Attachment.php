<?php

namespace TorrentTracker\XF\Repository;

class Attachment extends XFCP_Attachment
{
	protected function fastDeleteAttachmentsFromPairs(array $pairs)
	{
		if (!$pairs)
		{
			return 0;
		}
		$this->getTorrentRepo()->deleteTorrents(array_keys($pairs));
		parent::fastDeleteAttachmentsFromPairs($pairs);

	} 

	protected function getTorrentRepo()
	{
		return $this->repository('TorrentTracker:Torrent');
	} 
}