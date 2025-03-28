<?php


namespace TorrentTracker\XF\Repository;


class Node extends XFCP_Node
{
    public function getNodesListForTorrent(\XF\Entity\Node $withinNode = null){
        if ($withinNode && !$withinNode->hasChildren())
        {
            return $this->em->getEmptyCollection();
        }

        $nodes = $this->findNodesForTorrentList($withinNode)->where('is_torrent_category','=','1')->fetch();
        $this->loadNodeTypeDataForNodes($nodes);

        return $this->filterViewable($nodes);
    }

    public function findNodesForTorrentList(\XF\Entity\Node $withinNode = null)
    {
        /** @var \XF\Finder\Node $finder */
        $finder = $this->finder('XF:Node');
        if ($withinNode)
        {
            $finder->descendantOf($withinNode);
        }
        $finder->setDefaultOrder('lft');

        return $finder;
    }
}