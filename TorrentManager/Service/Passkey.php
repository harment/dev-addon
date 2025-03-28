<?php

namespace TorrentManager\Service;

use XF\App;

class Passkey
{
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function generatePasskey($userId)
    {
        return md5($userId . $this->app->config('cookie')['salt']);
    }

    public function getAnnounceUrl($userId, $torrentId)
    {
        $passkey = $this->generatePasskey($userId);
        return "http://your-tracker-domain.com/announce?passkey={$passkey}&torrent_id={$torrentId}";
    }
}