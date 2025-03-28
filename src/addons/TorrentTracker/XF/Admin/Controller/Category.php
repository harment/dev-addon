<?php


namespace TorrentTracker\XF\Admin\Controller;


class Category extends XFCP_Category
{
    protected function nodeSaveProcess(\XF\Entity\Node $node)
    {
        $formAction =  parent::nodeSaveProcess($node);

        $icon = $this->filter(['node' => ['is_torrent_category' => 'int'] ]);
        $formAction->setup(function() use ($node, $icon)
        {
            $node->is_torrent_category = $icon['node']['is_torrent_category'];
        });

        return $formAction;
    }
}