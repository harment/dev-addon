<?php
namespace TorrentManager\Job;

use XF\Job\AbstractJob;

class TorrentStats extends AbstractJob
{
    public function run($maxRunTime)
    {
        $result = \XF\Job\JobResult::create();
        self::updateStats(); // استدعاء الدالة الثابتة
        $result->completed = true;
        return $result;
    }

    public static function updateStats()
    {
        // وظيفة الكرون تعمل الآن
    }

    public function getStatusMessage()
    {
        return 'تحديث إحصائيات التورنت...';
    }

    public function canCancel()
    {
        return false;
    }

    public function canTriggerByChoice()
    {
        return true;
    }
}