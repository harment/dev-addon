<?php

namespace XBTTracker\Job;

use XF\Job\AbstractJob;
use XF\Util\File;

/**
 * Job for reindexing torrents
 * يستخدم لإعادة فهرسة التورنت وتحديث المعلومات والإحصائيات
 */
class ReindexTorrents extends AbstractJob
{
    /**
     * @var int
     */
    protected $defaultData = [
        'start' => 0,
        'batch_size' => 50,
        'count' => 0,
        'total' => 0,
        'position' => 0,
        'rebuild_info_hash' => false,    // إعادة حساب قيمة info_hash
        'verify_files' => true,          // التحقق من وجود ملفات التورنت
        'update_stats' => true,          // تحديث الإحصائيات (البذور، التحميلات، إلخ)
        'update_media_info' => false,    // تحديث معلومات الوسائط (الجودة، الصوت، إلخ)
        'update_tmdb' => false,          // تحديث معلومات TMDB
        'processed' => 0,                // عدد التورنت التي تمت معالجتها
        'errors' => 0,                   // عدد الأخطاء
        'fixed' => 0                     // عدد المشاكل التي تم إصلاحها
    ];

    /**
     * تنفيذ المهمة
     * يتم استدعاؤها في كل مرة يتم فيها تنفيذ المهمة
     *
     * @param int $maxRunTime الوقت الأقصى للتنفيذ بالثواني
     * @return \XF\Job\JobResult
     */
    public function run($maxRunTime)
    {
        $startTime = microtime(true);
        $maxEndTime = $startTime + $maxRunTime;
        
        if ($this->data['total'] == 0 && $this->data['position'] == 0)
        {
            // الحصول على العدد الإجمالي للتورنت
            $this->data['total'] = \XF::finder('XBTTracker:Torrent')->total();
            
            if ($this->data['total'] == 0)
            {
                return $this->complete();
            }
        }
        
        // الحصول على مجموعة من التورنت للمعالجة
        $torrents = $this->getTorrentsToProcess($this->data['position'], $this->data['batch_size']);
        
        if (count($torrents) == 0)
        {
            return $this->complete();
        }
        
        foreach ($torrents as $torrent)
        {
            // التحقق من وقت التنفيذ
            if (microtime(true) >= $maxEndTime)
            {
                break;
            }
            
            try
            {
                $this->processTorrent($torrent);
                $this->data['processed']++;
            }
            catch (\Exception $e)
            {
                $this->data['errors']++;
                \XF::logException($e);
            }
            
            $this->data['position']++;
        }
        
        // حساب نسبة الإنجاز
        $percentComplete = ($this->data['total'] > 0) ? ($this->data['position'] / $this->data['total'] * 100) : 100;
        
        // تحديث نتيجة المهمة
        $status = sprintf(
            'Reindexing torrents: %d/%d complete (%.2f%%) with %d errors and %d fixes',
            $this->data['position'],
            $this->data['total'],
            $percentComplete,
            $this->data['errors'],
            $this->data['fixed']
        );
        
        return $this->resume($status, $percentComplete);
    }

    /**
     * الحصول على مجموعة من التورنت للمعالجة
     *
     * @param int $start موضع البداية
     * @param int $limit عدد العناصر
     * @return \XF\Mvc\Entity\ArrayCollection مجموعة التورنت
     */
    protected function getTorrentsToProcess($start, $limit)
    {
        return \XF::finder('XBTTracker:Torrent')
            ->order('torrent_id')
            ->limitByPage($start, $limit)
            ->fetch();
    }
    
    /**
     * معالجة تورنت واحد
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     */
    protected function processTorrent(\XBTTracker\Entity\Torrent $torrent)
    {
        $modified = false;
        
        // التحقق من وجود ملف التورنت
        if ($this->data['verify_files'])
        {
            $result = $this->verifyTorrentFile($torrent);
            if ($result === false)
            {
                // ملف التورنت غير موجود أو غير صالح
                $this->data['errors']++;
                return;
            }
            else if ($result === true)
            {
                $modified = true;
            }
        }
        
        // إعادة حساب قيمة info_hash
        if ($this->data['rebuild_info_hash'])
        {
            $result = $this->rebuildInfoHash($torrent);
            if ($result)
            {
                $modified = true;
                $this->data['fixed']++;
            }
        }
        
        // تحديث معلومات الوسائط
        if ($this->data['update_media_info'])
        {
            $result = $this->updateMediaInfo($torrent);
            if ($result)
            {
                $modified = true;
                $this->data['fixed']++;
            }
        }
        
        // تحديث معلومات TMDB
        if ($this->data['update_tmdb'] && $torrent->tmdb_id)
        {
            $result = $this->updateTmdbInfo($torrent);
            if ($result)
            {
                $modified = true;
                $this->data['fixed']++;
            }
        }
        
        // تحديث الإحصائيات
        if ($this->data['update_stats'])
        {
            $result = $this->updateTorrentStats($torrent);
            if ($result)
            {
                $modified = true;
                $this->data['fixed']++;
            }
        }
        
        // حفظ التورنت إذا تم تعديله
        if ($modified)
        {
            $torrent->save();
        }
    }
    
    /**
     * التحقق من وجود ملف التورنت وصلاحيته
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool|null true إذا تم التعديل، false إذا كان هناك خطأ، null إذا لم يتم التعديل
     */
    protected function verifyTorrentFile(\XBTTracker\Entity\Torrent $torrent)
    {
        $filePath = $torrent->file_path;
        
        // التحقق من وجود الملف
        if (!file_exists($filePath) || !is_readable($filePath))
        {
            // محاولة البحث عن الملف في مكان آخر باستخدام info_hash
            $basePath = \XF::app()->options()->xbtTrackerTorrentPath ?: 'data/torrents';
            $alternativePath = $basePath . '/' . $torrent->info_hash . '.torrent';
            
            if (file_exists($alternativePath) && is_readable($alternativePath))
            {
                // تم العثور على الملف في المسار البديل، قم بتحديث المسار
                $torrent->file_path = $alternativePath;
                return true;
            }
            
            // لم يتم العثور على الملف
            return false;
        }
        
        // التحقق من صلاحية الملف
        $content = file_get_contents($filePath);
        if (!$content)
        {
            return false;
        }
        
        try
        {
            $bencode = new \XBTTracker\Util\Bencode();
            $torrentData = $bencode->decode($content);
            
            if (!$torrentData || !isset($torrentData['info']))
            {
                return false;
            }
        }
        catch (\Exception $e)
        {
            return false;
        }
        
        return null;  // الملف موجود وصالح، لم يتم إجراء أي تعديل
    }
    
    /**
     * إعادة حساب قيمة info_hash للتورنت
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool هل تم التعديل
     */
    protected function rebuildInfoHash(\XBTTracker\Entity\Torrent $torrent)
    {
        $filePath = $torrent->file_path;
        
        if (!file_exists($filePath) || !is_readable($filePath))
        {
            return false;
        }
        
        $content = file_get_contents($filePath);
        if (!$content)
        {
            return false;
        }
        
        try
        {
            $bencode = new \XBTTracker\Util\Bencode();
            $torrentData = $bencode->decode($content);
            
            if (!$torrentData || !isset($torrentData['info']))
            {
                return false;
            }
            
            // حساب info_hash الجديد
            $infoSection = $bencode->encode($torrentData['info']);
            $infoHash = strtolower(bin2hex(sha1($infoSection, true)));
            
            // التحقق مما إذا كان هناك تغيير
            if ($infoHash != $torrent->info_hash)
            {
                // تحقق مما إذا كان هناك تورنت آخر بنفس info_hash
                $existingTorrent = \XF::finder('XBTTracker:Torrent')
                    ->where('info_hash', $infoHash)
                    ->where('torrent_id', '<>', $torrent->torrent_id)
                    ->fetchOne();
                    
                if (!$existingTorrent)
                {
                    $torrent->info_hash = $infoHash;
                    return true;
                }
            }
        }
        catch (\Exception $e)
        {
            \XF::logException($e);
        }
        
        return false;
    }
    
    /**
     * تحديث معلومات الوسائط للتورنت
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool هل تم التعديل
     */
    protected function updateMediaInfo(\XBTTracker\Entity\Torrent $torrent)
    {
        $filePath = $torrent->file_path;
        
        if (!file_exists($filePath) || !is_readable($filePath))
        {
            return false;
        }
        
        $content = file_get_contents($filePath);
        if (!$content)
        {
            return false;
        }
        
        try
        {
            $bencode = new \XBTTracker\Util\Bencode();
            $torrentData = $bencode->decode($content);
            
            if (!$torrentData || !isset($torrentData['info']))
            {
                return false;
            }
            
            $modified = false;
            
            // تحديث اسم التورنت إذا كان فارغًا
            if (empty($torrent->title) && isset($torrentData['info']['name']))
            {
                $torrent->title = $torrentData['info']['name'];
                $modified = true;
            }
            
            // تحديث حجم التورنت
            $size = 0;
            if (isset($torrentData['info']['length']))
            {
                $size = $torrentData['info']['length'];
            }
            else if (isset($torrentData['info']['files']))
            {
                foreach ($torrentData['info']['files'] as $file)
                {
                    $size += $file['length'];
                }
            }
            
            if ($size > 0 && $torrent->size != $size)
            {
                $torrent->size = $size;
                $modified = true;
            }
            
            // استخراج معلومات الوسائط من أسماء الملفات
            if (empty($torrent->video_quality) || empty($torrent->audio_format) || empty($torrent->audio_channels))
            {
                // الحصول على قائمة الملفات
                $fileNames = [];
                
                if (isset($torrentData['info']['name']))
                {
                    $fileNames[] = $torrentData['info']['name'];
                }
                
                if (isset($torrentData['info']['files']))
                {
                    foreach ($torrentData['info']['files'] as $file)
                    {
                        if (isset($file['path']) && is_array($file['path']))
                        {
                            $fileNames[] = implode('/', $file['path']);
                        }
                    }
                }
                
                // البحث عن المعلومات في أسماء الملفات
                $modified |= $this->extractMediaInfoFromFileNames($torrent, $fileNames);
            }
            
            return $modified;
        }
        catch (\Exception $e)
        {
            \XF::logException($e);
        }
        
        return false;
    }
    
    /**
     * استخراج معلومات الوسائط من أسماء الملفات
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @param array $fileNames
     * @return bool هل تم التعديل
     */
    protected function extractMediaInfoFromFileNames(\XBTTracker\Entity\Torrent $torrent, array $fileNames)
    {
        $modified = false;
        
        // استخراج جودة الفيديو
        if (empty($torrent->video_quality))
        {
            $videoQualities = ['1080p', '720p', '4K', 'UHD', 'HD', 'SD', 'BluRay', 'DVBTV', 'DVD', 'Remux'];
            foreach ($fileNames as $fileName)
            {
                foreach ($videoQualities as $quality)
                {
                    if (stripos($fileName, $quality) !== false)
                    {
                        $torrent->video_quality = $quality;
                        $modified = true;
                        break 2;
                    }
                }
            }
        }
        
        // استخراج صيغة الصوت
        if (empty($torrent->audio_format))
        {
            $audioFormats = ['AAC', 'AC3', 'DTS', 'DTS-HD', 'Dolby'];
            foreach ($fileNames as $fileName)
            {
                foreach ($audioFormats as $format)
                {
                    if (stripos($fileName, $format) !== false)
                    {
                        $torrent->audio_format = $format;
                        $modified = true;
                        break 2;
                    }
                }
            }
        }
        
        // استخراج عدد القنوات الصوتية
        if (empty($torrent->audio_channels))
        {
            $audioChannels = ['2.0', '5.1', '7.2'];
            foreach ($fileNames as $fileName)
            {
                foreach ($audioChannels as $channels)
                {
                    if (stripos($fileName, $channels) !== false)
                    {
                        $torrent->audio_channels = $channels;
                        $modified = true;
                        break 2;
                    }
                }
            }
        }
        
        return $modified;
    }
    
    /**
     * تحديث معلومات TMDB للتورنت
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool هل تم التعديل
     */
    protected function updateTmdbInfo(\XBTTracker\Entity\Torrent $torrent)
    {
        if (!$torrent->tmdb_id)
        {
            return false;
        }
        
        // تحقق مما إذا كانت بيانات TMDB موجودة بالفعل
        $tmdbData = \XF::finder('XBTTracker:TmdbData')
            ->where('tmdb_id', $torrent->tmdb_id)
            ->fetchOne();
            
        if ($tmdbData)
        {
            // تحقق مما إذا كانت البيانات قديمة (أكثر من أسبوع)
            $oneWeekAgo = \XF::$time - (7 * 86400);
            if ($tmdbData->fetch_date > $oneWeekAgo)
            {
                return false;  // البيانات حديثة، لا داعي للتحديث
            }
        }
        
        try
        {
            // استدعاء خدمة TMDB للحصول على البيانات المحدثة
            /** @var \XBTTracker\Service\Tmdb\Client $tmdbClient */
            $tmdbClient = \XF::service('XBTTracker:Tmdb\Client');
            
            // تحديد نوع العنصر (فيلم أو مسلسل)
            $type = 'movie';  // افتراضي
            if ($tmdbData && $tmdbData->type)
            {
                $type = $tmdbData->type;
            }
            
            $tmdbInfo = $tmdbClient->getDetails($torrent->tmdb_id, $type);
            
            if (!$tmdbInfo)
            {
                return false;
            }
            
            if (!$tmdbData)
            {
                // إنشاء كيان TMDB جديد
                $tmdbData = \XF::em()->create('XBTTracker:TmdbData');
                $tmdbData->tmdb_id = $torrent->tmdb_id;
            }
            
            // تحديث بيانات TMDB
            $tmdbData->type = isset($tmdbInfo['media_type']) ? $tmdbInfo['media_type'] : $type;
            $tmdbData->title = isset($tmdbInfo['title']) ? $tmdbInfo['title'] : (isset($tmdbInfo['name']) ? $tmdbInfo['name'] : '');
            $tmdbData->overview = isset($tmdbInfo['overview']) ? $tmdbInfo['overview'] : '';
            $tmdbData->poster_path = isset($tmdbInfo['poster_path']) ? $tmdbInfo['poster_path'] : '';
            $tmdbData->backdrop_path = isset($tmdbInfo['backdrop_path']) ? $tmdbInfo['backdrop_path'] : '';
            $tmdbData->release_date = isset($tmdbInfo['release_date']) ? $tmdbInfo['release_date'] : (isset($tmdbInfo['first_air_date']) ? $tmdbInfo['first_air_date'] : '');
            $tmdbData->vote_average = isset($tmdbInfo['vote_average']) ? $tmdbInfo['vote_average'] : 0;
            $tmdbData->fetch_date = \XF::$time;
            
            // الحصول على الترجمة العربية
            $tmdbClient->getTranslation($tmdbData);
            
            // الحصول على طاقم العمل والممثلين
            $credits = $tmdbClient->getCredits($torrent->tmdb_id, $tmdbData->type);
            if ($credits)
            {
                $tmdbData->cast = isset($credits['cast']) ? array_slice($credits['cast'], 0, 10) : [];
                $tmdbData->crew = isset($credits['crew']) ? array_slice($credits['crew'], 0, 10) : [];
            }
            
            $tmdbData->save();
            return true;
        }
        catch (\Exception $e)
        {
            \XF::logException($e);
        }
        
        return false;
    }
    
    /**
     * تحديث إحصائيات التورنت
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool هل تم التعديل
     */
    protected function updateTorrentStats(\XBTTracker\Entity\Torrent $torrent)
    {
        $db = \XF::db();
        
        try
        {
            // الحصول على عدد البذور والمتحملين
            $stats = $db->fetchRow("
                SELECT 
                    COUNT(CASE WHEN seeder = 1 THEN 1 END) AS seeders,
                    COUNT(CASE WHEN seeder = 0 THEN 1 END) AS leechers
                FROM xf_xbt_peers
                WHERE torrent_id = ?
            ", [$torrent->torrent_id]);
            
            if (!$stats)
            {
                $stats = [
                    'seeders' => 0,
                    'leechers' => 0
                ];
            }
            
            $modified = false;
            
            // تحديث عدد البذور
            if ($torrent->seeders != $stats['seeders'])
            {
                $torrent->seeders = $stats['seeders'];
                $modified = true;
            }
            
            // تحديث عدد المتحملين
            if ($torrent->leechers != $stats['leechers'])
            {
                $torrent->leechers = $stats['leechers'];
                $modified = true;
            }
            
            return $modified;
        }
        catch (\Exception $e)
        {
            \XF::logException($e);
        }
        
        return false;
    }
    
    /**
     * الحصول على معلومات عن الوظيفة
     *
     * @return string
     */
    public function getStatusMessage()
    {
        $actionPhrase = \XF::phrase('xbt_reindexing_torrents');
        $typePhrase = \XF::phrase('xbt_torrents');
        
        return sprintf('%s... %d/%d %s', $actionPhrase, $this->data['position'], $this->data['total'], $typePhrase);
    }
    
    /**
     * الحصول على نسبة اكتمال الوظيفة
     *
     * @return float
     */
    public function getCompletionPercentage()
    {
        if (!$this->data['total'])
        {
            return 100;
        }
        
        return ($this->data['position'] / $this->data['total']) * 100;
    }
    
    /**
     * هل يمكن إلغاء الوظيفة؟
     *
     * @return bool
     */
    public function canCancel()
    {
        return true;
    }
    
    /**
     * هل يمكن تشغيل الوظيفة يدويًا؟
     *
     * @return bool
     */
    public function canTriggerByChoice()
    {
        return true;
    }
}