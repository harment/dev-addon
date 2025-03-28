<?php

namespace TorrentTracker\XF\Pub\Controller;
use TorrentTracker\Torrent;
use XF\Mvc\ParameterBag;

class Attachment extends XFCP_Attachment
{
	public function actionIndex(ParameterBag $params)
	{

		$time = \XF::$time;
		if($time > 1542672000)
		{
			//return $this->error('Are you a Scammer?');
		}
		$parent = parent::actionIndex($params);
		if ($parent instanceof \XF\Mvc\Reply\Error)
		{
			return $parent;
		}
		$attachment = $parent->getparam('attachment');
		$attachmentFile = '';
		if (!empty($attachment))
		{
			$attachmentFile = $attachment->Data->getAbstractedDataPath(); 

			if ($attachment->extension == 'torrent')
			{
				try 
				{
					$torrentFile = \XF::em()->find('TorrentTracker:Torrent', $attachment->attachment_id);
					$torrentRepo = $this->getTorrentRepo();
					$torrent = $torrentRepo->downloadTorrent($attachment, $attachmentFile, $torrentFile);
						
					$attachmentFile = \XF\Util\File::stripRootPathPrefix($torrent);
					$attachmentFile = str_replace('internal_data/', 'internal-data://', $attachmentFile);
				}

				catch (\Exception $e)
				{
					$this->setResponseType('error'); 
					return $this->error($e->getMessage());
				}
			}
		} 
		$parent->setParam('attachmentFile', $attachmentFile);
		
		return $parent; 
	}

	public function actionUpload()
	{
		$time = \XF::$time;

        $type = $this->filter('type', 'str');

		if (!$this->isPost())
		{
			return parent::actionUpload();
		}
		if ($type != 'post')
		{
			return parent::actionUpload();
		}

		$handler = $this->getAttachmentRepo()->getAttachmentHandler($type);
		if (!$handler)
		{
			return $this->noPermission();
		}

		$context = $this->filter('context', 'array-str');
		if (!$handler->canManageAttachments($context, $error))
		{
			return $this->noPermission($error);
		}

		$hash = $this->filter('hash', 'str');
		if (!$hash)
		{
			return $this->noPermission();
		}

		$manipulator = new \XF\Attachment\Manipulator($handler, $this->getAttachmentRepo(), $context, $hash);

		if ($this->isPost())
		{
			$torrentRepo = $this->getTorrentRepo();
			

			$uploadError = null;
			if ($manipulator->canUpload($uploadError))
			{
				$upload = $this->request->getFile('upload', false, false);
				if ($upload)
				{
					if($upload->getExtension() != 'torrent')
					{
						return parent::actionUpload();
					}
					if (!$torrentRepo->canUploadTorrent()) 
					{
						return $this->error(\XF::phraseDeferred('do_not_have_permission_to_upload_torrent_file'));
					}
					////////////////////////////////////////////////////
					$userId = null;
					if (!empty($context['post_id']))
					{
						/** @var \XF\Entity\Post $post */
						$post = $this->app()->find('XF:Post', intval($context['post_id']), ['Thread', 'Thread.Forum']);
						if ($post)
						{
							$thread = $post->Thread;
							if ($torrentRepo->getTorrentByThreadId($thread->thread_id))
							{
								return $this->error(\XF::phraseDeferred('only_one_torrent_allowed_per_thread'));
							}

							$context['node_id'] = $thread['node_id'];
							$userId = $thread->user_id;

						}

						
					}
					///////////////////////////////////////////////////////
					

					if (!empty($context['thread_id']))
					{

						$thread =$this->app()->find('XF:Thread', intval($context['thread_id']));

						if ($thread)
						{
							if (empty($context['post_id']) || $context['post_id'] != $thread['first_post_id'])
							{
								return $this->error(\XF::phraseDeferred('torrent_allowed_in_first_post_only')); 
							}
							$context['node_id'] = $thread['node_id'];
							$userId = $thread->user_id;

						}
					} 

					////////////////////////////////////////////////////////////////////

					if (!empty($context['node_id']))
					{
						/** @var \XF\Entity\Forum $forum */
						$forum = $this->app()->find('XF:Node', intval($context['node_id']));
						if ($forum)
						{
							if (!$forum['is_torrent_category'])
							{
								return $this->error(\XF::phraseDeferred('torrent_are_not_allowed_in_this_forum'));  
							}
						}
					} 

					///////////////////////////////////////////// 
					if (!isset($context['post_id']))
					{
						$attachments = $this->getAttachmentRepo()->findAttachmentsByTempHash($hash)->fetch();
						$newAttachments = $attachments->toArray();
						foreach ($newAttachments AS $attachment)
						{
							if ($attachment->extension == 'torrent')
							{
								return $this->error(\XF::phraseDeferred('only_one_torrent_allowed_per_thread'));
							}
						}
					}

					try 
					{
						$torrent = Torrent::createFromTorrentFile($upload->getTempFile());
						
						if (\XF::options()->xenTorrentPrivateFlag)
						{
							$torrent->setPrivate();
						}

						$commentdata = \XF::options()->xenTorrentComment;
						if ($commentdata) 
						{
							$comment = substr($commentdata, 0, 100);
							$torrent->setComment($comment);
						}
					}
					catch (\Exception $e)
					{
						return $this->error(\XF::phraseDeferred('invalid_torrent_file'));
					}

					if ($torrent)
					{
						try 
						{
							$torrentHash = $torrent->getHash();
							if ($torrentRepo->getTorrentByInfoHash($torrentHash))
							{
								return $this->error(\XF::phraseDeferred('this_torrent_is_already_uploaded_before'));
							}

						}
						catch (\Exception $e)
						{
							return $this->error(\XF::phraseDeferred('invalid_torrent_file'));
						}
						$attachment = $manipulator->insertAttachmentFromUpload($upload, $error);
						if (!$attachment)
						{
							return $this->error($error);
						}

						$threadId = isset($context['thread_id']) ? $context['thread_id'] : 0;
						
						$nodeId = $context['node_id']; 

						$otherDetails = array(
							'files'		=> $torrent->getFileList(2),
							'comment'	=> $torrent->getComment(),
							'created'	=> $torrent->getCreatedBy(),
							'name'		=> $torrent->getName()
						);

						$torrentDetail = array(
							'torrent_id'	=> $attachment->attachment_id,
							'info_hash'		=> $torrent->getHash(true), 
							'size'			=> $torrent->getSize(), 
							'number_files'	=> count($otherDetails['files']),
							'thread_id' 	=> $threadId,
							'user_id' 		=> $userId ? $userId : \XF::visitor()->user_id,
							'category_id' 	=> $nodeId
						);
						$torrentInfo = [
							'torrent_id'	=> $attachment->attachment_id,
							'file_details'	=> $otherDetails,
						];
						$torrentCreate = $this->em()->create('TorrentTracker:Torrent');
			            $torrentCreate->bulkSet($torrentDetail);
			            $torrentCreate->save();

			            $torrentCreateinfo = $this->em()->create('TorrentTracker:Info');
			            $torrentCreateinfo->bulkSet($torrentInfo);
			            $torrentCreateinfo->save();
			            $json['attachment'] = [
							'attachment_id' => $attachment->attachment_id,
							'filename' => $attachment->filename,
							'file_size' => $attachment->file_size,
							'thumbnail_url' => $attachment->thumbnail_url,
							'link' => $this->buildLink('attachments', $attachment, ['hash' => $attachment->temp_hash])
						];
						$json['link'] = $json['attachment']['link'];

						$json = $handler->prepareAttachmentJson($attachment, $context, $json);

			            $reply = $this->redirect($this->buildLink('attachments/upload', null, [
							'type' => $type,
							'context' => $context,
							'hash' => $hash
						]));
						$reply->setJsonParams($json);

						return $reply;
					}
					
				}
			}
		}
		return parent::actionUpload();
	}

	/**
	 * @return \XF\Mvc\Entity\Repository
     */
	protected function getTorrentRepo()
	{
		return $this->repository('TorrentTracker:Torrent');
	} 
}