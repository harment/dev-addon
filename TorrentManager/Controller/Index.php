<?php

namespace TorrentManager\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Index extends AbstractController
{
    public function actionIndex()
    {
        return $this->view('TorrentManager:Index', 'torrent_manager_index', []);
    }
}