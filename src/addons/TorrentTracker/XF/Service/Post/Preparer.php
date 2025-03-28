<?php

namespace TorrentTracker\XF\Service\Post;

class Preparer extends XFCP_Preparer
{

	protected function associateAttachments($hash)
	{
		$post = $this->post;
		$threadId = $post->thread_id; 
		$attachmentFinder = $this->finder('XF:Attachment')
			->where('temp_hash', $hash);

		foreach ($attachmentFinder->fetch() AS $attachment)
		{
			if ($attachment->extension == 'torrent')
			{ 
				$torrent = \XF::em()->find('TorrentTracker:Torrent', $attachment->attachment_id);
				if($torrent)
				{
					$torrent->thread_id = $threadId;
					$torrent->save();
				}
			}
		}
		return parent::associateAttachments($hash);
	}
}