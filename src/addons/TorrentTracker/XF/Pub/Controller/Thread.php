<?php

namespace TorrentTracker\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Thread extends XFCP_Thread
{
	public function actionIndex(ParameterBag $params)
	{
		$parent = parent::actionIndex($params);

		if (!$parent instanceof \XF\Mvc\Reply\View)
		{
			return $parent;
		}

//		$thread = $parent->getparam('thread');
		$firstPost = $parent->getparam('firstPost');
//		$torrent =[];
		foreach ($firstPost->Attachments AS $attachmentId => $attachment)
		{ 
			if ($attachment->extension == 'torrent')
			{
				unset($firstPost->Attachments[$attachmentId]);
			}
		}   

		return $parent;
	}

	protected function assertViewableThread($threadId, array $extraWith = [])
	{
		$extraWith[] = 'Torrent';

		return parent::assertViewableThread($threadId, $extraWith);
	}


	/**
	 * @return \XF\Mvc\Entity\Repository
     */
	protected function getTorrentRepo()
	{
		return $this->repository('TorrentTracker:Torrent');
	} 
}