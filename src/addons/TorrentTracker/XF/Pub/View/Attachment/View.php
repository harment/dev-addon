<?php

namespace TorrentTracker\XF\Pub\View\Attachment;

class View extends XFCP_view
{
	public function renderRaw()
	{

		/** @var \XF\Entity\Attachment $attachment */
		$attachment = $this->params['attachment'];

		if (!empty($this->params['return304']))
		{
			$this->response
				->httpCode(304)
				->removeHeader('last-modified');

			return '';
		}
		if ($attachment->extension == 'torrent')
		{
			$filename = $attachment->filename;
			
			if(!empty(\XF::options()->xenTorrentAttachmentPostfix))
			{

				$filename = str_replace('.torrent', ' '.\XF::options()->xenTorrentAttachmentPostfix.'.torrent', $attachment->filename);
			}

			$this->response
			->setAttachmentFileParams($filename, $attachment->extension) 
			->header('ETag', '"' . $attachment->attach_date . '"', true) ;
			$resource = \XF::fs()->readStream($this->params['attachmentFile']);
			return $this->response->responseStream($resource);

		}
		return parent::renderRaw();
	}
}