<?php

namespace Harment\XBTTracker\Job;

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
     * Default job data
     * 
     * @var array
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
     * Run the job
     * تنفيذ المهمة - يتم استدعاؤها في كل مرة يتم فيها تنفيذ المهمة
     *
     * @param int $maxRunTime Maximum run time in seconds
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
        
        // If file list is empty, prepare it first
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
                'message' => \XF::phrase('xbt_invalid_user_for_import')
            ]);
        }
        
        // Import files
        // استيراد الملفات
        $filesProcessed = 0;
        
        while ($this->data['file_position'] < $totalSteps)
        {
            // Check execution time
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
        
        // Calculate progress
        // حساب التقدم
        $currentPosition = $this->data['file_position'];
        $percentComplete = ($totalSteps > 0) ? ($currentPosition / $totalSteps) * 100 : 100;
        
        // Update job result
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
     * Prepare the file list for import
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
     * Prepare file list from a folder
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
     * Prepare file list from a URL
     * تجهيز قائمة الملفات من رابط
     */
    protected function prepareFromUrl()
    {
        $url = $this->data['source_path'];
        $options = $this->data['source_options'];
        
        // Make HTTP request to get file index
        // This depends on the source site structure
        // This is a simple example
        
        // Call URL using CURL
        // استدعاء الرابط باستخدام CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Setup additional options
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
        
        // Extract file links based on a pattern
        // This is a simple pattern for example
        // استخراج روابط الملفات بناءً على نمط معين
        $pattern = '/<a.*?href="(.*?\.torrent)".*?>/i';
        if (preg_match_all($pattern, $response, $matches))
        {
            foreach ($matches[1] as $torrentUrl)
            {
                // Convert relative links to absolute links
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
     * Prepare file list from an index file
     * The file contains a list of torrent paths
     * 
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
                continue;  // Skip empty lines and comments
            }
            
            // Determine path type (local or remote)
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
     * Import a single torrent file
     * استيراد ملف تورنت واحد
     *
     * @param array $fileInfo File information
     * @param User $user Import user
     * @return bool Success or failure
     */
    protected function importSingleTorrent(array $fileInfo, User $user)
    {
        $torrentContent = $this->getTorrentContent($fileInfo);
        
        if (!$torrentContent)
        {
            return false;
        }
        
        try {
            // Decode torrent content
            // فك تشفير محتوى التورنت
            $bencode = new \Harment\XBTTracker\Util\Bencode();
            $torrentData = $bencode->decode($torrentContent);
            
            if (!$torrentData || !isset($torrentData['info']))
            {
                return false;
            }
            
            // Calculate info_hash
            // احسب الـ info_hash
            $infoSection = $bencode->encode($torrentData['info']);
            $infoHash = strtolower(bin2hex(sha1($infoSection, true)));
            
            // Check if torrent already exists
            // تحقق ما إذا كان التورنت موجودًا بالفعل
            $existingTorrent = \XF::finder('XBTTracker:Torrent')
                ->where('info_hash', $infoHash)
                ->fetchOne();
                
            if ($existingTorrent && $this->data['skip_existing'])
            {
                return true;  // Torrent skipped because it already exists
            }
            
            // Save torrent file
            // حفظ ملف التورنت
            $torrentPath = $this->saveTorrentFile($torrentContent, $infoHash);
            
            if (!$torrentPath)
            {
                return false;
            }
            
            // Create torrent entity
            // إنشاء كيان التورنت
            /** @var \Harment\XBTTracker\Entity\Torrent $torrent */
            $torrent = \XF::em()->create('XBTTracker:Torrent');
            
            // Setup torrent properties
            // إعداد خصائص التورنت
            $torrent->info_hash = $infoHash;
            $torrent->file_path = $torrentPath;
            $torrent->user_id = $user->user_id;
            $torrent->category_id = $this->data['category_id'];
            $torrent->is_freeleech = $this->data['is_freeleech'];
            $torrent->creation_date = \XF::$time;
            
            // Calculate torrent size
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
            
            // Set title
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
                // Use torrent name from data if available
                // استخدام اسم التورنت من البيانات إذا كان متاحًا
                $torrent->title = isset($torrentData['info']['name']) ? $torrentData['info']['name'] : $fileInfo['filename'];
            }
            
            // Try to extract media info
            // محاولة استخراج معلومات الوسائط
            $this->extractMediaInfo($torrent, $fileInfo['filename']);
            
            // Save torrent
            // حفظ التورنت
            $success = false;
            
            try
            {
                $torrent->save();
                $success = true;
                
                // Delete original file if requested
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
        } catch (\Exception $e) {
            \XF::logException($e);
            return false;
        }
    }
    
    /**
     * Get torrent file content
     * الحصول على محتوى ملف التورنت
     *
     * @param array $fileInfo File information
     * @return string|false File content or false on failure
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
     * Save torrent file
     * حفظ ملف التورنت
     *
     * @param string $content File content
     * @param string $infoHash info_hash value
     * @return string|false Path to saved file or false on failure
     */
    protected function saveTorrentFile($content, $infoHash)
    {
        // Get torrent storage path
        // الحصول على مسار تخزين التورنت
        $path = \XF::app()->options()->xbtTrackerTorrentPath;
        
        if (!$path)
        {
            $path = 'data/torrents';
        }
        
        // Create directory if it doesn't exist
        // إنشاء المجلد إذا لم يكن موجودًا
        File::createDirectory($path);
        
        // Create a unique filename
        // إنشاء اسم ملف فريد
        $fileName = $infoHash . '.torrent';
        $filePath = $path . '/' . $fileName;
        
        // Save file
        // حفظ الملف
        if (File::writeFile($filePath, $content, false))
        {
            return $filePath;
        }
        
        return false;
    }
    
    /**
     * Extract media information from filename
     * استخراج معلومات الوسائط من اسم الملف
     *
     * @param \Harment\XBTTracker\Entity\Torrent $torrent Torrent entity
     * @param string $fileName Filename
     */
    protected function extractMediaInfo(\Harment\XBTTracker\Entity\Torrent $torrent, $fileName)
    {
        // Extract video quality
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
        
        // Extract audio format
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
        
        // Extract audio channels
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
     * Get the user for import
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
        
        // Use system user if no other user specified
        // استخدام مستخدم النظام إذا لم يتم تحديد مستخدم آخر
        return \XF::em()->find('XF:User', 1);
    }
    
    /**
     * Create complete job result
     * إنشاء نتيجة كاملة للوظيفة
     *
     * @param array $extra Additional information
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
        
        return parent::complete($data);
    }
    
    /**
     * Get job status message
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
     * Get job completion percentage
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
     * Can the job be cancelled?
     * هل يمكن إلغاء الوظيفة؟
     *
     * @return bool
     */
    public function canCancel()
    {
        return true;
    }
    
    /**
     * Can the job be triggered manually?
     * هل يمكن إيقاف الوظيفة مؤقتًا؟
     *
     * @return bool
     */
    public function canTriggerByChoice()
    {
        return true;
    }
}