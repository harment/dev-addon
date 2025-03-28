<?php

namespace TorrentTracker\XF\Entity;


class Attachment extends XFCP_Attachment
{

	protected function _postDelete()
	{
		parent::_postDelete();
		/** @var AttachmentData $data */
//		$data = $this->Data;
		$this->getTorrentRepo()->deleteTorrent($this->get('attachment_id'));
	}

	/**
	 * @return \XF\Mvc\Entity\Repository
     */
	protected function getTorrentRepo()
	{
		return $this->repository('TorrentTracker:Torrent');
	} 
}