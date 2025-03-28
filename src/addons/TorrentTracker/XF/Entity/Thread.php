<?php

namespace TorrentTracker\XF\Entity;

use XF\PrintableException;

class Thread extends XFCP_Thread
{

    protected $torrentId;

    public function canRequestForFreeleech(&$error = null)
    {
        $visitor = \XF::visitor();
        $canSendFreeleechReq = $visitor->hasPermission('xenTorrentTracker', 'sentfreeleechrequest');
        $options = \XF::options();
        $timecanFreeleechReq = $options->freeleechreqtime * 86400;
        if ($canSendFreeleechReq && (\XF::$time - $this->post_date) < $timecanFreeleechReq) {
            return true;
        }
        return false;
    }

    protected function _preSave()
    {
        parent::_preSave();
        $attachmentHash = isset($_POST['attachment_hash']) ? $_POST['attachment_hash'] : '';
        if (!empty($attachmentHash)) {
            $torrentId = $this->db()->fetchOne('
				SELECT attachment_id FROM xf_attachment AS a
				INNER JOIN xftt_torrent as t ON (t.torrent_id = a.attachment_id)
				WHERE temp_hash = ?
			', $attachmentHash);

            $this->torrentId = $torrentId;

        }
    }

    protected function _postSave()
    {
        parent::_postSave();

        $node = $this->em()->find('XF:Node', $this->get('node_id'));

        if ($this->isInsert() && $this->torrentId) {
            $this->db()->update('xftt_torrent', array(
                'thread_id' => $this->get('thread_id'),
                'upload_multiplier' => $node->upload_multiplier,
                'download_multiplier' => $node->download_multiplier,
            ), 'torrent_id = ' . $this->torrentId);

            $visitor = \XF::visitor();
            try {
                $visitor->torrent_upload_count++;
                $visitor->save();
            } catch (\Exception $e) {
                \XF::logError($e->getMessage());
            }
        }

        if ($this->Torrent) {
            if ($this->isUpdate() && $this->isChanged('node_id') && $this->Torrent->torrent_id) {
                $this->db()->update('xftt_torrent', array(
                    'category_id' => $this->get('node_id'),
                    'upload_multiplier' => $node->upload_multiplier,
                    'download_multiplier' => $node->download_multiplier
                ), 'torrent_id = ' . $this->Torrent->torrent_id);
            }
        }

    }

}