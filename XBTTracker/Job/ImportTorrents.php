<?php

namespace XBTTracker\Job;

use XF\Job\AbstractJob;
use XF\Entity\User;
use XF\Util\File;

/**
 * Job for importing torrents from external sources
 * يستخدم لاستيراد ملفات التورنت من مصادر خارجية مثل المجلدات المحلية أو مواقع أخرى
 */
class ImportTorrents extends AbstractJob
{
    /**
     * @var int
     */
    protected $defaultData = [
        'source_type' => 'folder',        // folder, url, file
        'source_path' => '',              // مسار المجلد أو الملف أو الرابط
        'source_options' => [],           // خيارات إضافية للمصدر
        'user_id' => 0,                   // معرف المستخدم الذي سيتم تسجيل التورنت باسمه
        'category_id' => 0,               // معرف الفئة التي سيتم إضافة التورنت إليها
        'file_list' => [],                // قائمة الملفات للاستيراد
        'file_position' => 0,             // موضع الملف الحالي في القائمة
        'imported_count' => 0,            // عدد الملفات التي تم استيرادها
        'error_count' => 0,               // عدد الأخطاء التي حدثت
        'skip_existing' => true,          // تخطي الملفات الموجودة
        'use_filename_as_title' => true,  // استخدام اسم الملف كعنوان
        'is_freeleech' => false,          // تعيين التورنت كمجاني (بدون احتساب التحميل)
        'auto_approve' => true,           // الموافقة التلقائية على التورنت
        'delete_after_import' => false    // حذف الملف الأصلي بعد الاستيراد
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
        $totalSteps = count($this->data['file_list']);
        
        if ($this->data['file_position'] >= $totalSteps)
        {
            return $this->complete();
        }
        
        // إذا كانت قائمة الملفات فارغة، قم بتجهيزها أولاً
        if (empty($this->data['file_list']))
        {
            $this->prepareFileList();
            $totalSteps = count($this->data['file_list']);
            
            if ($totalSteps == 0)
            {
                return $this->complete();
            }
        }
        
        $user = $this->getUserForImport();
        if (!$user)
        {
            return $this->complete([
                'result' => 'error',
                'message' => 'Invalid user for import'
            ]);
        }
        
        // استيراد الملفات
        $filesProcessed = 0;
        
        while ($this->data['file_position'] < $totalSteps)
        {
            // التحقق من وقت التنفيذ
            if (microtime(true) >= $maxEndTime)
            {
                break;
            }
            
            $fileInfo = $this->data['file_list'][$this->data['file_position']];
            $result = $this->importSingleTorrent($fileInfo, $user);
            
            if ($result === true)
            {
                $this->data['imported_count']++;
            }
            else
            {
                $this->data['error_count']++;
            }
            
            $this->data['file_position']++;
            $filesProcessed++;
        }
        
        // حساب التقدم
        $currentPosition = $this->data['file_position'];
        $percentComplete = ($totalSteps > 0) ? ($currentPosition / $totalSteps) * 100 : 100;
        
        // تحديث نتيجة الوظيفة
        $status = sprintf(
            'Importing torrents: %d/%d complete (%.2f%%) with %d errors',
            $currentPosition,
            $totalSteps,
            $percentComplete,
            $this->data['error_count']
        );
        
        return $this->resume($status, $percentComplete);
    }

    /**
     * تجهيز قائمة الملفات للاستيراد
     */
    protected function prepareFileList()
    {
        $this->data['file_list'] = [];
        
        switch ($this->data['source_type'])
        {
            case 'folder':
                $this->prepareFromFolder();
                break;
                
            case 'url':
                $this->prepareFromUrl();
                break;
                
            case 'file':
                $this->prepareFromFile();
                break;
        }
    }
    
    /**
     * تجهيز قائمة الملفات من مجلد
     */
    protected function prepareFromFolder()
    {
        $path = $this->data['source_path'];
        
        if (!is_dir($path))
        {
            return;
        }
        
        $files = glob($path . '/*.torrent');
        
        foreach ($files as $file)
        {
            if (is_file($file))
            {
                $this->data['file_list'][] = [
                    'path' => $file,
                    'filename' => basename($file),
                    'type' => 'local'
                ];
            }
        }
    }
    
    /**
     * تجهيز قائمة الملفات من رابط
     */
    protected function prepareFromUrl()
    {
        $url = $this->data['source_path'];
        $options = $this->data['source_options'];
        
        // تنفيذ استدعاء HTTP للحصول على فهرس الملفات
        // هذا يعتمد على بنية الموقع المصدر
        // هذا مجرد مثال بسيط
        
        // استدعاء الرابط باستخدام CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // إعداد الخيارات الإضافية
        if (!empty($options['user_agent']))
        {
            curl_setopt($ch, CURLOPT_USERAGENT, $options['user_agent']);
        }
        
        if (!empty($options['username']) && !empty($options['password']))
        {
            curl_setopt($ch, CURLOPT_USERPWD, $options['username'] . ':' . $options['password']);
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if (!$response)
        {
            return;
        }
        
        // استخراج روابط الملفات بناءً على نمط معين
        // هذا نمط بسيط للمثال
        $pattern = '/<a.*?href="(.*?\.torrent)".*?>/i';
        if (preg_match_all($pattern, $response, $matches))
        {
            foreach ($matches[1] as $torrentUrl)
            {
                // تحويل الروابط النسبية إلى روابط مطلقة
                if (strpos($torrentUrl, 'http') !== 0)
                {
                    $base = rtrim($url, '/');
                    $torrentUrl = $base . '/' . ltrim($torrentUrl, '/');
                }
                
                $this->data['file_list'][] = [
                    'path' => $torrentUrl,
                    'filename' => basename($torrentUrl),
                    'type' => 'remote'
                ];
            }
        }
    }
    
    /**
     * تجهيز قائمة الملفات من ملف فهرس
     * الملف يحتوي على قائمة مسارات التورنت
     */
    protected function prepareFromFile()
    {
        $file = $this->data['source_path'];
        
        if (!file_exists($file) || !is_readable($file))
        {
            return;
        }
        
        $content = file_get_contents($file);
        $lines = explode("\n", $content);
        
        foreach ($lines as $line)
        {
            $line = trim($line);
            if (empty($line) || $line[0] == '#')
            {
                continue;  // تخطي السطور الفارغة والتعليقات
            }
            
            // تحديد نوع المسار (محلي أو عن بعد)
            $type = (strpos($line, 'http') === 0) ? 'remote' : 'local';
            
            $this->data['file_list'][] = [
                'path' => $line,
                'filename' => basename($line),
                'type' => $type
            ];
        }
    }
    
    /**
     * استيراد ملف تورنت واحد
     *
     * @param array $fileInfo معلومات الملف
     * @param User $user المستخدم المستورد
     * @return bool نجاح أو فشل الاستيراد
     */
    protected function importSingleTorrent(array $fileInfo, User $user)
    {
        $torrentContent = $this->getTorrentContent($fileInfo);
        
        if (!$torrentContent)
        {
            return false;
        }
        
        // فك تشفير محتوى التورنت
        $bencode = new \XBTTracker\Util\Bencode();
        $torrentData = $bencode->decode($torrentContent);
        
        if (!$torrentData || !isset($torrentData['info']))
        {
            return false;
        }
        
        // احسب الـ info_hash
        $infoSection = $bencode->encode($torrentData['info']);
        $infoHash = strtolower(bin2hex(sha1($infoSection, true)));
        
        // تحقق ما إذا كان التورنت موجودًا بالفعل
        $existingTorrent = \XF::finder('XBTTracker:Torrent')
            ->where('info_hash', $infoHash)
            ->fetchOne();
            
        if ($existingTorrent && $this->data['skip_existing'])
        {
            return true;  // تم تخطي التورنت لأنه موجود بالفعل
        }
        
        // حفظ ملف التورنت
        $torrentPath = $this->saveTorrentFile($torrentContent, $infoHash);
        
        if (!$torrentPath)
        {
            return false;
        }
        
        // إنشاء كيان التورنت
        /** @var \XBTTracker\Entity\Torrent $torrent */
        $torrent = \XF::em()->create('XBTTracker:Torrent');
        
        // إعداد خصائص التورنت
        $torrent->info_hash = $infoHash;
        $torrent->file_path = $torrentPath;
        $torrent->user_id = $user->user_id;
        $torrent->category_id = $this->data['category_id'];
        $torrent->is_freeleech = $this->data['is_freeleech'];
        $torrent->creation_date = \XF::$time;
        
        // حساب حجم التورنت
        if (isset($torrentData['info']['length']))
        {
            $torrent->size = $torrentData['info']['length'];
        }
        else if (isset($torrentData['info']['files']))
        {
            $size = 0;
            foreach ($torrentData['info']['files'] as $file)
            {
                $size += $file['length'];
            }
            $torrent->size = $size;
        }
        else
        {
            $torrent->size = 0;
        }
        
        // تعيين العنوان
        if ($this->data['use_filename_as_title'])
        {
            $title = pathinfo($fileInfo['filename'], PATHINFO_FILENAME);
            $title = str_replace(['_', '.'], ' ', $title);
            $title = preg_replace('/\b(1080p|720p|4K|HD|BluRay|x264|AAC|MP3|FLAC)\b/i', '', $title);
            $title = trim($title);
            
            $torrent->title = $title ?: $fileInfo['filename'];
        }
        else
        {
            // استخدام اسم التورنت من البيانات إذا كان متاحًا
            $torrent->title = isset($torrentData['info']['name']) ? $torrentData['info']['name'] : $fileInfo['filename'];
        }
        
        // محاولة استخراج معلومات الجودة والصوت
        $this->extractMediaInfo($torrent, $fileInfo['filename']);
        
        // حفظ التورنت
        $success = false;
        
        try
        {
            $torrent->save();
            $success = true;
            
            // حذف الملف الأصلي إذا تم طلب ذلك
            if ($this->data['delete_after_import'] && $fileInfo['type'] == 'local')
            {
                @unlink($fileInfo['path']);
            }
        }
        catch (\Exception $e)
        {
            \XF::logException($e);
        }
        
        return $success;
    }
    
    /**
     * الحصول على محتوى ملف التورنت
     *
     * @param array $fileInfo معلومات الملف
     * @return string|false محتوى الملف أو false في حالة الفشل
     */
    protected function getTorrentContent(array $fileInfo)
    {
        switch ($fileInfo['type'])
        {
            case 'local':
                if (file_exists($fileInfo['path']) && is_readable($fileInfo['path']))
                {
                    return file_get_contents($fileInfo['path']);
                }
                break;
                
            case 'remote':
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $fileInfo['path']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                $content = curl_exec($ch);
                curl_close($ch);
                
                if ($content)
                {
                    return $content;
                }
                break;
        }
        
        return false;
    }
    
    /**
     * حفظ ملف التورنت
     *
     * @param string $content محتوى الملف
     * @param string $infoHash قيمة info_hash
     * @return string|false مسار الملف المحفوظ أو false في حالة الفشل
     */
    protected function saveTorrentFile($content, $infoHash)
    {
        // الحصول على مسار تخزين التورنت
        $path = \XF::app()->options()->xbtTrackerTorrentPath;
        
        if (!$path)
        {
            $path = 'data/torrents';
        }
        
        // إنشاء المجلد إذا لم يكن موجودًا
        File::createDirectory($path);
        
        // إنشاء اسم ملف فريد
        $fileName = $infoHash . '.torrent';
        $filePath = $path . '/' . $fileName;
        
        // حفظ الملف
        if (File::writeFile($filePath, $content, false))
        {
            return $filePath;
        }
        
        return false;
    }
    
    /**
     * استخراج معلومات الوسائط من اسم الملف
     *
     * @param \XBTTracker\Entity\Torrent $torrent كيان التورنت
     * @param string $fileName اسم الملف
     */
    protected function extractMediaInfo(\XBTTracker\Entity\Torrent $torrent, $fileName)
    {
        // استخراج جودة الفيديو
        $videoQualities = ['1080p', '720p', '4K', 'UHD', 'HD', 'SD', 'BluRay', 'DVBTV', 'DVD', 'Remux'];
        foreach ($videoQualities as $quality)
        {
            if (stripos($fileName, $quality) !== false)
            {
                $torrent->video_quality = $quality;
                break;
            }
        }
        
        // استخراج صيغة الصوت
        $audioFormats = ['AAC', 'AC3', 'DTS', 'DTS-HD', 'Dolby'];
        foreach ($audioFormats as $format)
        {
            if (stripos($fileName, $format) !== false)
            {
                $torrent->audio_format = $format;
                break;
            }
        }
        
        // استخراج عدد القنوات الصوتية
        $audioChannels = ['2.0', '5.1', '7.2'];
        foreach ($audioChannels as $channels)
        {
            if (stripos($fileName, $channels) !== false)
            {
                $torrent->audio_channels = $channels;
                break;
            }
        }
    }
    
    /**
     * الحصول على المستخدم المستورد
     *
     * @return User|null
     */
    protected function getUserForImport()
    {
        $userId = $this->data['user_id'];
        
        if ($userId)
        {
            $user = \XF::em()->find('XF:User', $userId);
            if ($user)
            {
                return $user;
            }
        }
        
        // استخدام مستخدم النظام إذا لم يتم تحديد مستخدم آخر
        return \XF::em()->find('XF:User', 1);
    }
    
    /**
     * إنشاء نتيجة كاملة للوظيفة
     *
     * @param array $extra معلومات إضافية
     * @return \XF\Job\JobResult
     */
    protected function complete(array $extra = [])
    {
        $data = [
            'imported' => $this->data['imported_count'],
            'errors' => $this->data['error_count'],
            'total' => count($this->data['file_list'])
        ];
        
        if ($extra)
        {
            $data = array_merge($data, $extra);
        }
        
        return $this->complete($data);
    }
    
    /**
     * الحصول على معلومات عن الوظيفة
     *
     * @return array
     */
    public function getStatusMessage()
    {
        $actionPhrase = \XF::phrase('xbt_importing_torrents');
        $typePhrase = \XF::phrase('xbt_torrents');
        
        $imported = $this->data['imported_count'];
        $total = count($this->data['file_list']);
        
        return sprintf('%s... %d/%d %s', $actionPhrase, $imported, $total, $typePhrase);
    }
    
    /**
     * الحصول على نسبة اكتمال الوظيفة
     *
     * @return float
     */
    public function getCompletionPercentage()
    {
        $total = count($this->data['file_list']);
        if (!$total)
        {
            return 100;
        }
        
        return ($this->data['file_position'] / $total) * 100;
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
     * هل يمكن إيقاف الوظيفة مؤقتًا؟
     *
     * @return bool
     */
    public function canTriggerByChoice()
    {
        return true;
    }
}