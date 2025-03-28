<?php


namespace XFDev\HitnRun\Admin\Controller;


class HitnRun extends \XF\Admin\Controller\AbstractController
{

    public function actionList()
    {

        $page = $this->filterPage();
        $perPage = 20;

        $hitnRunFinder = $this->finder('TorrentTracker:Peer')
            ->with('Torrent',true)
            ->with('User',true)
            ->where('hit','=','yes')
            ->order('hnr_last_checked','desc');

        $total = $hitnRunFinder->total();

        $hitnRunList = $hitnRunFinder->limitByPage($page,$perPage)->fetch();

        $viewParams = [
            'hitnrunList' => $hitnRunList,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'hnr_count' => $hitnRunList->count()
        ];

        return $this->view('','xfdev_xftt_hitnrun_members_list',$viewParams);
    }

}