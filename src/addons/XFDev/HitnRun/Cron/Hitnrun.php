<?php

namespace XFDev\HitnRun\Cron;

use XF\Db\Exception;
use XF\Entity\User;
use XF\Service\User\BlockLeech;

class Hitnrun
{

    public static function checkHitnRun()
    {
        if (self::checkHnrEnabledStatus()) {
            /**
             * @var \XFDev\HitnRun\Repository\Hitnrun $hnrRepo
             */
            $hnrRepo = \XF::app()->repository('XFDev\HitnRun\Repository\Hitnrun');
            $hnrRepo->checkHitnRun();
        }
    }


    //Set the hnr_checked = 0, if he starts a torrent again after zapping it
    public static function actionReCheckZappedPeers()
    {
        if (self::checkHnrEnabledStatus()) {
            /**
             * @var \XFDev\HitnRun\Repository\Hitnrun $hnrRepo
             */
            $hnrRepo = \XF::app()->repository('XFDev\HitnRun\Repository\Hitnrun');
            $hnrRepo->recheckZappedTorrents();
        }
    }

//    Check for Hit Torrents and unhit them, if they satisfy requirements now.
    public static function recheckHitPeers()
    {
        if (self::checkHnrEnabledStatus()) {

            /**
             * @var \XFDev\HitnRun\Repository\Hitnrun $hnrRepo
             */
            $hnrRepo = \XF::app()->repository('XFDev\HitnRun\Repository\Hitnrun');
            $hnrRepo->recheckHitPeers();
        }
    }


//    Disable or Enable Torrent download access for users who have x no. of hnr for x no. of days
    public static function disableTorrentDownloadAccess()
    {
        if (self::checkHnrEnabledStatus()) {

            /**
             * @var \XFDev\HitnRun\Repository\Hitnrun $hnrRepo
             */
            $hnrRepo = \XF::app()->repository('XFDev\HitnRun\Repository\Hitnrun');
            $hnrRepo->disableTorrentDownloadAccess();
            $hnrRepo->enableTorrentDownloadAccess();
        }
    }

    private static function checkHnrEnabledStatus()
    {
        return \XF::options()->xfdev_hitnrun_enable;
    }
}