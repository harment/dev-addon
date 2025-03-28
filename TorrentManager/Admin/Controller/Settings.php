<?php

namespace TorrentManager\Admin\Controller;

use XF\Admin\Controller\AbstractController;

class Settings extends AbstractController
{
    public function actionIndex()
    {
        return $this->view('TorrentManager:Settings', 'torrent_manager_settings', []);
    }
}