<?php

namespace TorrentTracker\XF\Admin\Controller;

use XF\Mvc\FormAction;

class Forum extends XFCP_Forum
{

    protected function nodeSaveProcess(\XF\Entity\Node $node)
    {
        $icon = $this->filter(['node' => ['is_torrent_category' => 'int'] ]);
        $up_multiplier = $this->filter(['node' => ['upload_multiplier' => 'int'] ]);
        $down_multiplier = $this->filter(['node' => ['download_multiplier' => 'int'] ]);
        $formAction = parent::nodeSaveProcess($node);
        $formAction->setup(function() use ($node, $icon)
        {
            $node->is_torrent_category = $icon['node']['is_torrent_category'];
        });
        $formAction->setup(function() use ($node, $up_multiplier,$down_multiplier)
        {
            $node->upload_multiplier = $up_multiplier['node']['upload_multiplier'];
            $node->download_multiplier = $down_multiplier['node']['download_multiplier'];
        });



        if($node->isUpdate() && $node->is_torrent_category)
        {
            // $thread = $this->em()->find('XF:Thread', $node->get('node_id'));

            $finder = \XF::finder('XF:Thread');
            $thread = $finder->where('node_id',$node->get('node_id'))->fetch();

            foreach ($thread as $tid) {
                \XF::db()->update('xftt_torrent', array(
                    'upload_multiplier' => $up_multiplier['node']['upload_multiplier'],
                    'download_multiplier' => $down_multiplier['node']['download_multiplier'],
                    'flags' => 2,
                ), 'thread_id = ' . $tid['thread_id']);
            }
        }

        return $formAction;
    }

    protected function saveTypeData(FormAction $form, \XF\Entity\Node $node, \XF\Entity\AbstractNode $data)
    {
        $forumInput = $this->filter([
            'torrent_category_image' => 'str',
        ]);

        /** @var \XF\Entity\Forum $data */
        $data->bulkSet($forumInput);
        return parent::saveTypeData($form, $node, $data);
    }
}