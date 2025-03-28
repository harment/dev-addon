<?php


namespace XFDev\HitnRun\XF\Entity;


use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
    public function getTotalHnr()
    {
        $finder = \XF::finder('TorrentTracker:Peer');
        $hnr = $finder->with('Torrent', true)->where('hit', '=', 'yes')
            ->where('user_id', '=', $this->user_id)->total();

        return $hnr;
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);
        $structure->getters['totalHnr'] = true;
        return $structure;
    }
}