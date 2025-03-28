<?php

namespace TorrentManager\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Upload extends AbstractController
{
    public function actionIndex()
    {
        $this->assertPermission('torrentManager', 'uploadTorrents');
        return $this->view('TorrentManager:Upload', 'upload_torrent');
    }

    public function actionUpload()
    {
        $this->assertPostOnly();
        $this->assertPermission('torrentManager', 'uploadTorrents');

        $input = $this->filter([
            'title' => 'str',
            'category_id' => 'uint',
            'poster_url' => 'str',
            'video_type' => 'str',
            'audio_type' => 'str',
            'audio_channels' => 'str',
            'tmdb_link' => 'str'
        ]);

        $upload = $this->request->getFile('torrent_file', true);
        if (!$upload || !$upload->isValid(['torrent']))
        {
            return $this->error('يرجى رفع ملف تورنت صالح');
        }

        $torrentsDir = \XF::getRootDirectory() . '/torrents';
        if (!is_dir($torrentsDir))
        {
            mkdir($torrentsDir, 0755, true);
        }

        $fileName = 'torrent_' . \XF::visitor()->user_id . '_' . time() . '.torrent';
        $filePath = $torrentsDir . '/' . $fileName;
        $upload->moveTo($filePath);

        // استخراج الهاش باستخدام مكتبة bencode (يجب تثبيتها عبر Composer)
        require_once \XF::getRootDirectory() . '/vendor/autoload.php';
        $torrentData = \Bencode\Bencode::decode(file_get_contents($filePath));
        $hash = sha1($torrentData['info'], true);

        $torrent = $this->em()->create('TorrentManager:Torrent');
        $torrent->user_id = \XF::visitor()->user_id;
        $torrent->title = $input['title'];
        $torrent->category_id = $input['category_id'];
        $torrent->file_path = $filePath;
        $torrent->hash = bin2hex($hash);
        $torrent->video_type = $input['video_type'];
        $torrent->audio_type = $input['audio_type'];
        $torrent->audio_channels = $input['audio_channels'];
        $torrent->poster_url = $input['poster_url'];
        $torrent->tmdb_link = $input['tmdb_link'] ?: null;
        $torrent->upload_date = time();
        $torrent->status = 'active';

        if (!$torrent->preSave())
        {
            return $this->error('خطأ في البيانات المدخلة');
        }

        $torrent->save();
         // داخل actionUpload بعد حفظ الملف
        $torrentData['announce'] = $this->service('TorrentManager:Passkey')->getAnnounceUrl(\XF::visitor()->user_id, $torrent->torrent_id);
        file_put_contents($filePath, \Bencode\Bencode::encode($torrentData));
        return $this->redirect($this->buildLink('torrents'), 'تم رفع التورنت بنجاح');
    }

    protected function assertPermission($permissionGroup, $permission)
    {
        if (!\XF::visitor()->hasPermission($permissionGroup, $permission))
        {
            throw $this->exception($this->noPermission());
        }
    }
}