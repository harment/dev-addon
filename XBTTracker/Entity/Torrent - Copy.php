<?php

namespace Harment\XBTTracker\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Torrent extends Entity
{
    /**
     * تعريف بنية الكيان
     *
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xbt_torrents';
        $structure->shortName = 'XBTTracker:Torrent';
        $structure->primaryKey = 'torrent_id';
        $structure->columns = [
            'torrent_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => false],
            'title' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'description' => ['type' => self::STR, 'default' => ''],
            'info_hash' => ['type' => self::STR, 'maxLength' => 40, 'required' => true],
            'file_path' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'poster_path' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'size' => ['type' => self::UINT, 'required' => true],
            'category_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'video_quality' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
            'audio_format' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
            'audio_channels' => ['type' => self::STR, 'maxLength' => 10, 'default' => ''],
            'tmdb_id' => ['type' => self::UINT, 'default' => 0],
            'seeders' => ['type' => self::UINT, 'default' => 0],
            'leechers' => ['type' => self::UINT, 'default' => 0],
            'completed' => ['type' => self::UINT, 'default' => 0],
            'is_freeleech' => ['type' => self::BOOL, 'default' => false],
            'creation_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'view_count' => ['type' => self::UINT, 'default' => 0]
        ];
        $structure->getters = [
            'size_formatted' => true,
            'creation_date_formatted' => true
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Category' => [
                'entity' => 'Harment\XBTTracker:Category',
                'type' => self::TO_ONE,
                'conditions' => 'category_id',
                'primary' => true
            ],
            'TmdbData' => [
                'entity' => 'Harment\XBTTracker:TmdbData',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['tmdb_id', '=', '$tmdb_id']
                ],
                'primary' => true
            ]
        ];
        
        return $structure;
    }
    
    /**
     * الحصول على حجم التورنت منسقًا
     *
     * @return string
     */
    public function getSizeFormatted()
    {
        return \XF::language()->fileSizeFormat($this->size);
    }
    
    /**
     * الحصول على تاريخ الإنشاء منسقًا
     *
     * @return string
     */
    public function getCreationDateFormatted()
    {
        return \XF::language()->dateTime($this->creation_date);
    }
    
    /**
     * الحصول على رابط التورنت
     *
     * @return string
     */
    public function getLink()
    {
        return \XF::app()->router('public')->buildLink('torrents/view', ['info_hash' => $this->info_hash]);
    }
    
    /**
     * الحصول على رابط التحميل
     *
     * @return string
     */
    public function getDownloadLink()
    {
        return \XF::app()->router('public')->buildLink('torrents/download', ['info_hash' => $this->info_hash]);
    }
    
    /**
     * الحصول على رابط الصورة المصغرة
     *
     * @return string|null
     */
    public function getPosterUrl()
    {
        if (!$this->poster_path) {
            return null;
        }
        
        return \XF::app()->baseUrl() . '/' . $this->poster_path;
    }
    
    /**
     * التحقق مما إذا كان التورنت خاليًا من سجل النسبة
     *
     * @return bool
     */
    public function isFreeLeech()
    {
        if ($this->is_freeleech) {
            return true;
        }
        
        $options = \XF::options();
        if ($options->xbtTrackerGlobalFreeleech) {
            return true;
        }
        
        return false;
    }
    
    /**
     * التحقق مما إذا كان المستخدم يمكنه تعديل التورنت
     *
     * @return bool
     */
    public function canEdit()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->user_id) {
            return false;
        }
        
        if ($visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
            return true;
        }
        
        if ($visitor->user_id == $this->user_id && $visitor->hasPermission('xbtTracker', 'edit')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * التحقق مما إذا كان المستخدم يمكنه حذف التورنت
     *
     * @return bool
     */
    public function canDelete()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->user_id) {
            return false;
        }
        
        if ($visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
            return true;
        }
        
        if ($visitor->user_id == $this->user_id && $visitor->hasPermission('xbtTracker', 'delete')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * التحقق مما إذا كان المستخدم يمكنه تحميل التورنت
     *
     * @return bool
     */
    public function canDownload()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->user_id) {
            return false;
        }
        
        if (!$visitor->hasPermission('xbtTracker', 'download')) {
            return false;
        }
        
        // يمكن إضافة تحقق من النسبة هنا إذا لزم الأمر
        
        return true;
    }
}