<?php

namespace TorrentManager\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Admin\Controller\AbstractController;

class Rewards extends AbstractController
{
    public function actionIndex()
    {
        return $this->view('TorrentManager:Rewards', 'torrent_manager_rewards', []);
    }
}