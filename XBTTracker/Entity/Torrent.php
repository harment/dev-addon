<?php
// src/addons/XBTTracker/Entity/Torrent.php
namespace XBTTracker\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * كيان التورنت
 * يستخدم لتخزين وإدارة جميع المعلومات المتعلقة بملفات التورنت
 *
 * @property int $torrent_id
 * @property string $title
 * @property string $description
 * @property string $info_hash
 * @property string $file_path
 * @property string $poster_path
 * @property int $size
 * @property int $category_id
 * @property int $user_id
 * @property string $video_quality
 * @property string $audio_format
 * @property string $audio_channels
 * @property int $tmdb_id
 * @property int $seeders
 * @property int $leechers
 * @property int $completed
 * @property bool $is_freeleech
 * @property int $creation_date
 * @property int $view_count
 *
 * @property-read string $url
 * @property-read string $torrent_url
 * @property-read string $poster_url
 * @property-read string $size_formatted
 * @property-read string $formatted_size
 * @property-read string $creation_date_formatted
 * @property-read array $video_quality_badge
 * @property-read array $audio_format_badge
 * @property-read array $audio_channels_badge
 * @property-read float $ratio
 *
 * @property-read \XF\Entity\User $User
 * @property-read \XBTTracker\Entity\Category $Category
 * @property-read \XBTTracker\Entity\TmdbData|null $TmdbData
 * @property-read \XF\Mvc\Entity\ArrayCollection|\XBTTracker\Entity\Peer[] $Peers
 * @property-read \XF\Mvc\Entity\ArrayCollection|\XBTTracker\Entity\UserCompleted[] $Completions
 */
class Torrent extends Entity
{
    /**
     * تعريف هيكل الكيان
     *
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_xbt_torrents';
        $structure->shortName = 'XBTTracker:Torrent';
        $structure->contentType = 'xbt_torrent';
        $structure->primaryKey = 'torrent_id';
        
        $structure->columns = [
            'torrent_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 255],
            'description' => ['type' => self::STR, 'default' => ''],
            'info_hash' => ['type' => self::STR, 'required' => true, 'maxLength' => 40],
            'file_path' => ['type' => self::STR, 'required' => true],
            'poster_path' => ['type' => self::STR, 'default' => ''],
            'size' => ['type' => self::UINT, 'required' => true],
            'category_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'video_quality' => ['type' => self::STR, 'default' => ''],
            'audio_format' => ['type' => self::STR, 'default' => ''],
            'audio_channels' => ['type' => self::STR, 'default' => ''],
            'tmdb_id' => ['type' => self::UINT, 'default' => 0],
            'seeders' => ['type' => self::UINT, 'default' => 0],
            'leechers' => ['type' => self::UINT, 'default' => 0],
            'completed' => ['type' => self::UINT, 'default' => 0],
            'is_freeleech' => ['type' => self::BOOL, 'default' => false],
            'creation_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'view_count' => ['type' => self::UINT, 'default' => 0],
        ];
        
        $structure->getters = [
            'url' => true,
            'torrent_url' => true,
            'poster_url' => true,
            'size_formatted' => true,
            'formatted_size' => true,
            'creation_date_formatted' => true,
            'video_quality_badge' => true,
            'audio_format_badge' => true,
            'audio_channels_badge' => true,
            'ratio' => true
        ];
        
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Category' => [
                'entity' => 'XBTTracker:Category',
                'type' => self::TO_ONE,
                'conditions' => 'category_id',
                'primary' => true
            ],
            'TmdbData' => [
                'entity' => 'XBTTracker:TmdbData',
                'type' => self::TO_ONE,
                'conditions' => [['tmdb_id', '=', '$tmdb_id']],
                'primary' => true
            ],
            'Peers' => [
                'entity' => 'XBTTracker:Peer',
                'type' => self::TO_MANY,
                'conditions' => 'torrent_id',
                'key' => 'peer_id'
            ],
            'Completions' => [
                'entity' => 'XBTTracker:UserCompleted',
                'type' => self::TO_MANY,
                'conditions' => 'torrent_id',
                'key' => 'completed_id'
            ]
        ];
        
        return $structure;
    }
    
    /**
     * الحصول على معلمات URL للتورنت
     *
     * @return array
     */
    public function getUrlParams(): array
    {
        return [
            'torrent_id' => $this->torrent_id,
            'title' => \XF::app()->stringFormatter->wholeWordTrim($this->title, 30, 0, '')
        ];
    }
    
    /**
     * الحصول على عنوان URL للتورنت
     *
     * @return string
     */
    public function getUrl(): string
    {
        return \XF::app()->router()->buildLink('torrents/view', $this);
    }
    
    /**
     * دالة بديلة للتوافق مع الكود القديم
     *
     * @return string
     */
    public function getTorrentUrl(): string
    {
        return $this->getUrl();
    }
    
    /**
     * الحصول على نسبة البذور إلى المحملين (الريشيو)
     *
     * @return float
     */
    public function getRatio(): float
    {
        if ($this->leechers == 0) {
            return $this->seeders > 0 ? 999 : 0;
        }
        
        return round($this->seeders / $this->leechers, 2);
    }
    
    /**
     * الحصول على حجم التورنت بصيغة مقروءة
     *
     * @return string
     */
    public function getSizeFormatted(): string
    {
        return \XF::language()->fileSizeFormat($this->size);
    }
    
    /**
     * دالة بديلة للتوافق مع الكود القديم
     *
     * @return string
     */
    public function getFormattedSize(): string
    {
        return $this->getSizeFormatted();
    }
    
    /**
     * الحصول على تاريخ الإنشاء بتنسيق مقروء
     *
     * @return string
     */
    public function getCreationDateFormatted(): string
    {
        return \XF::language()->dateTime($this->creation_date);
    }
    
    /**
     * الحصول على عنوان URL لصورة البوستر
     *
     * @return string
     */
    public function getPosterUrl(): string
    {
        if (!$this->poster_path) {
            if ($this->tmdb_id && $this->TmdbData && $this->TmdbData->poster_path) {
                return $this->TmdbData->poster_url;
            }
            
            // إرجاع بوستر افتراضي
            return \XF::app()->templater()->getTemplateUrl('public:xbt_default_poster.png');
        }
        
        return \XF::app()->router()->buildLink('full:torrents/poster', $this, [
            'hash' => md5($this->poster_path . $this->torrent_id)
        ]);
    }
    
    /**
     * الحصول على شارة جودة الفيديو
     *
     * @return array
     */
    public function getVideoQualityBadge(): array
    {
        $qualities = [
            'DVBTV' => 'fa-tv',
            'DVD' => 'fa-compact-disc',
            '1080p' => 'fa-film',
            '4K' => 'fa-film',
            '720p' => 'fa-film',
            'SD' => 'fa-film',
            'HD' => 'fa-film',
            'Bluray' => 'fa-compact-disc',
            'Remux' => 'fa-compact-disc'
        ];
        
        $quality = $this->video_quality;
        $icon = isset($qualities[$quality]) ? $qualities[$quality] : 'fa-film';
        
        return [
            'icon' => $icon,
            'text' => $quality,
            'class' => 'videoQuality--' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $quality))
        ];
    }
    
    /**
     * الحصول على شارة صيغة الصوت
     *
     * @return array
     */
    public function getAudioFormatBadge(): array
    {
        $formats = [
            'AAC' => 'fa-volume-up',
            'AC3' => 'fa-volume-up',
            'DTS' => 'fa-volume-up',
            'DTS-HD' => 'fa-volume-up',
            'Dolby' => 'fa-volume-up'
        ];
        
        $format = $this->audio_format;
        $icon = isset($formats[$format]) ? $formats[$format] : 'fa-volume-up';
        
        return [
            'icon' => $icon,
            'text' => $format,
            'class' => 'audioFormat--' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $format))
        ];
    }
    
    /**
     * الحصول على شارة قنوات الصوت
     *
     * @return array
     */
    public function getAudioChannelsBadge(): array
    {
        $channels = $this->audio_channels;
        
        if (empty($channels)) {
            return [
                'icon' => 'fa-volume-up',
                'text' => '',
                'class' => 'audioChannels--unknown'
            ];
        }
        
        return [
            'icon' => 'fa-volume-up',
            'text' => $channels,
            'class' => 'audioChannels--' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $channels))
        ];
    }
    
    /**
     * التحقق مما إذا كان التورنت فعالًا (يوجد بذور)
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->seeders > 0;
    }
    
    /**
     * التحقق مما إذا كان التورنت مجانيًا (بدون احتساب التحميل)
     *
     * @return bool
     */
    public function isFreeLeech(): bool
    {
        if ($this->is_freeleech) {
            return true;
        }
        
        // التحقق من الإعدادات العامة للتراكر
        return \XF::options()->xbtTrackerGlobalFreeleech ?? false;
    }
    
    /**
     * الحصول على عدد الملفات في التورنت
     *
     * @return int|null
     */
    public function getFileCount(): ?int
    {
        try {
            $filePath = $this->file_path;
            
            if (!file_exists($filePath) || !is_readable($filePath)) {
                return null;
            }
            
            $content = file_get_contents($filePath);
            if (!$content) {
                return null;
            }
            
            $bencode = new \XBTTracker\Util\Bencode();
            $torrentData = $bencode->decode($content);
            
            if (!$torrentData || !isset($torrentData['info'])) {
                return null;
            }
            
            if (isset($torrentData['info']['files']) && is_array($torrentData['info']['files'])) {
                return count($torrentData['info']['files']);
            }
            
            // إذا لم توجد قائمة ملفات، فهذا تورنت ملف واحد
            return 1;
        } catch (\Exception $e) {
            \XF::logException($e);
            return null;
        }
    }
    
    /**
     * التحقق من إمكانية تحرير التورنت
     * 
     * @return bool
     */
    public function canEdit(): bool
    {
        $visitor = \XF::visitor();
        
        // السماح بالتحرير إذا كان مشرفًا
        if ($visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
            return true;
        }
        
        // السماح بالتحرير إذا كان المالك
        if ($visitor->user_id == $this->user_id && $visitor->hasPermission('xbtTracker', 'edit')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * التحقق من إمكانية حذف التورنت
     * 
     * @return bool
     */
    public function canDelete(): bool
    {
        $visitor = \XF::visitor();
        
        // السماح بالحذف إذا كان مشرفًا
        if ($visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
            return true;
        }
        
        // السماح بالحذف إذا كان المالك
        if ($visitor->user_id == $this->user_id && $visitor->hasPermission('xbtTracker', 'delete')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * التحقق من صحة الكيان قبل الحفظ
     *
     * @return bool
     */
    protected function _preSave(): bool
    {
        if ($this->isInsert() && !$this->creation_date) {
            $this->creation_date = \XF::$time;
        }
        
        if (empty($this->title)) {
            $this->error(\XF::phrase('xbt_torrent_title_required'), 'title');
            return false;
        }
        
        // التحقق من تنسيق info_hash
        if ($this->isChanged('info_hash')) {
            $infoHash = $this->info_hash;
            if (!preg_match('/^[0-9a-f]{40}$/i', $infoHash)) {
                $this->error(\XF::phrase('xbt_invalid_info_hash_format'), 'info_hash');
                return false;
            }
            
            // التحقق مما إذا كان هناك تورنت آخر بنفس قيمة info_hash
            if ($this->isInsert() || ($this->isUpdate() && $this->isChanged('info_hash'))) {
                $existing = \XF::finder('XBTTracker:Torrent')
                    ->where('info_hash', $infoHash)
                    ->where('torrent_id', '<>', $this->torrent_id)
                    ->fetchOne();
                    
                if ($existing) {
                    $this->error(\XF::phrase('xbt_torrent_with_info_hash_already_exists'), 'info_hash');
                    return false;
                }
            }
        }
        
        return parent::_preSave();
    }
    
    /**
     * الإجراءات بعد الحفظ
     */
    protected function _postSave(): void
    {
        parent::_postSave();
        
        // إذا كان هذا إدراج جديد، قم بتحديث إحصائيات الفئة
        if ($this->isInsert()) {
            $this->updateCategoryStats();
        } 
        // إذا تم تغيير الفئة، قم بتحديث إحصائيات الفئات القديمة والجديدة
        else if ($this->isChanged('category_id')) {
            $oldCategoryId = $this->getExistingValue('category_id');
            $this->updateCategoryStats($oldCategoryId);
            $this->updateCategoryStats();
        }
    }
    
    /**
     * الإجراءات بعد الحذف
     */
    protected function _postDelete(): void
    {
        parent::_postDelete();
        
        $db = $this->db();
        
        // حذف السجلات المرتبطة
        $db->delete('xf_xbt_peers', 'torrent_id = ?', $this->torrent_id);
        $db->delete('xf_xbt_user_completed', 'torrent_id = ?', $this->torrent_id);
        
        // تحديث إحصائيات الفئة
        $this->updateCategoryStats();
        
        // حذف ملف التورنت وصورة البوستر
        $this->deleteTorrentFiles();
    }
    
    /**
     * حذف ملفات التورنت
     */
    protected function deleteTorrentFiles(): void
    {
        try {
            // حذف ملف التورنت
            if ($this->file_path && file_exists($this->file_path)) {
                @unlink($this->file_path);
            }
            
            // حذف صورة البوستر
            if ($this->poster_path && file_exists($this->poster_path)) {
                @unlink($this->poster_path);
            }
        } catch (\Exception $e) {
            \XF::logException($e);
        }
    }
    
    /**
     * تحديث إحصائيات الفئة
     *
     * @param int|null $categoryId معرف الفئة (إذا كان مختلفًا عن الفئة الحالية)
     */
    protected function updateCategoryStats(?int $categoryId = null): void
    {
        $categoryId = $categoryId ?: $this->category_id;
        if (!$categoryId) {
            return;
        }
        
        $category = \XF::em()->find('XBTTracker:Category', $categoryId);
        if (!$category) {
            return;
        }
        
        // تحديث الإحصائيات (يمكن تنفيذ هذا بشكل أكثر تفصيلًا حسب الحاجة)
        // هذا مجرد مثال بسيط لتحديث عدد التورنت
        $db = $this->db();
        $torrentCount = $db->fetchOne('SELECT COUNT(*) FROM xf_xbt_torrents WHERE category_id = ?', $categoryId);
        
        // يمكنك هنا تحديث أي إحصائيات إضافية للفئة
    }
    
    /**
     * الحصول على ملف التورنت كمحتوى ثنائي
     *
     * @return string|null
     */
    public function getTorrentFileContent(): ?string
    {
        if (!$this->file_path || !file_exists($this->file_path)) {
            return null;
        }
        
        return file_get_contents($this->file_path);
    }
    
    /**
     * الحصول على معلومات إضافية للتورنت
     *
     * @return array|null
     */
    public function getAdditionalInfo(): ?array
    {
        try {
            $content = $this->getTorrentFileContent();
            if (!$content) {
                return null;
            }
            
            $bencode = new \XBTTracker\Util\Bencode();
            $torrentData = $bencode->decode($content);
            
            if (!$torrentData) {
                return null;
            }
            
            $info = [];
            
            // معلومات أساسية
            if (isset($torrentData['comment'])) {
                $info['comment'] = $torrentData['comment'];
            }
            
            if (isset($torrentData['created by'])) {
                $info['created_by'] = $torrentData['created by'];
            }
            
            if (isset($torrentData['creation date'])) {
                $info['creation_date'] = date('Y-m-d H:i:s', $torrentData['creation date']);
            }
            
            // معلومات الملفات
            if (isset($torrentData['info']['files']) && is_array($torrentData['info']['files'])) {
                $files = [];
                $totalSize = 0;
                
                foreach ($torrentData['info']['files'] as $file) {
                    if (isset($file['path']) && is_array($file['path'])) {
                        $path = implode('/', $file['path']);
                        $size = $file['length'] ?? 0;
                        $totalSize += $size;
                        
                        $files[] = [
                            'path' => $path,
                            'size' => $size,
                            'size_formatted' => \XF::language()->fileSizeFormat($size)
                        ];
                    }
                }
                
                $info['files'] = $files;
                $info['files_count'] = count($files);
                $info['total_size'] = $totalSize;
                $info['total_size_formatted'] = \XF::language()->fileSizeFormat($totalSize);
            } else if (isset($torrentData['info']['name']) && isset($torrentData['info']['length'])) {
                // تورنت ملف واحد
                $info['files'] = [
                    [
                        'path' => $torrentData['info']['name'],
                        'size' => $torrentData['info']['length'],
                        'size_formatted' => \XF::language()->fileSizeFormat($torrentData['info']['length'])
                    ]
                ];
                $info['files_count'] = 1;
                $info['total_size'] = $torrentData['info']['length'];
                $info['total_size_formatted'] = \XF::language()->fileSizeFormat($torrentData['info']['length']);
            }
            
            return $info;
        } catch (\Exception $e) {
            \XF::logException($e);
            return null;
        }
    }
    
    /**
     * التحقق من إمكانية تحميل التورنت للمستخدم الحالي
     *
     * @return bool
     */
    public function canDownload(): bool
    {
        $visitor = \XF::visitor();
        
        // التحقق من إذن التحميل العام
        if (!$visitor->hasPermission('xbtTracker', 'download')) {
            return false;
        }
        
        // التحقق إذا كان المستخدم مسجل الدخول
        if (!$visitor->user_id) {
            return false;
        }
        
        // التحقق من حالة ريشيو المستخدم
        if (!$this->isFreeLeech()) {
            /** @var \XBTTracker\Entity\UserStats $userStats */
            $userStats = \XF::em()->find('XBTTracker:UserStats', $visitor->user_id);
            
            if ($userStats) {
                $minRatio = \XF::options()->xbtTrackerRequiredRatio ?? 0;
                
                if ($minRatio > 0 && $userStats->ratio < $minRatio) {
                    // التحقق من المجموعات المعفاة
                    $exemptGroups = \XF::options()->xbtTrackerRatioExemptGroups ?? [];
                    
                    if (!$exemptGroups || !array_intersect($visitor->secondary_group_ids, $exemptGroups)) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }
}