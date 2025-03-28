<?php

namespace TorrentManager\Cron;

class TorrentStats
{
    public static function updateStats()
    {
        $db = \XF::db();
        $torrentUsers = \XF::finder('TorrentManager:TorrentUser')->fetch();

        foreach ($torrentUsers as $torrentUser)
        {
            // افتراض أن XBT Tracker يحدث uploaded و downloaded
            $torrentUser->ratio = $torrentUser->downloaded > 0 ? $torrentUser->uploaded / $torrentUser->downloaded : 0;

            // مكافآت: نقطة لكل ساعة سييد
            $hoursSeeded = floor($torrentUser->seed_time / 3600);
            $torrentUser->bonus_points += $hoursSeeded;

            // عقوبات: إذا كان السييد أقل من 72 ساعة
            if ($torrentUser->downloaded > 0 && $torrentUser->seed_time < 72 * 3600)
            {
                $torrentUser->warnings += 1;
            }

            $torrentUser->save();
        }
    }
}