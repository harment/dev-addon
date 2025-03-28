<?php

namespace TorrentManager\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class ListController extends AbstractController
{
    public function actionIndex()
    {
        $this->assertPermission('torrentManager', 'viewTorrents');

        $latestTorrents = $this->finder('TorrentManager:Torrent')
            ->where('status', 'active')
            ->order('upload_date', 'DESC')
            ->limit(20)
            ->fetch();

        $torrents = $this->finder('TorrentManager:Torrent')
            ->where('status', 'active')
            ->order('upload_date', 'DESC')
            ->fetch();

        $viewParams = [
            'latestTorrents' => $latestTorrents,
            'torrents' => $torrents
        ];

        return $this->view('TorrentManager:List', 'torrent_list', $viewParams);
    }
}