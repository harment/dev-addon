<?php

namespace TorrentManager\Permission;

use XF\Permission\PermissionBuilder;

class TorrentPermissions
{
    public static function build(PermissionBuilder $builder)
    {
        $builder->permissionGroup('torrentManager', 'Torrent Manager')
            ->permission('viewTorrents', 'View torrents', true)
            ->permission('uploadTorrents', 'Upload torrents', false)
            ->permission('editTorrents', 'Edit torrents', false)
            ->permission('downloadTorrents', 'Download torrents', true);
    }
}