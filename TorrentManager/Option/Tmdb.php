<?php

namespace TorrentManager\Option;

use XF\Option\AbstractOption;

class Tmdb extends AbstractOption
{
    public static function renderOption(\XF\Entity\Option $option, array $htmlParams)
    {
        return self::getTemplate('admin:torrent_manager_tmdb_option', $option, $htmlParams);
    }
}