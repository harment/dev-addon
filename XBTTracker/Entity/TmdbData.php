<?php
// src/addons/XBTTracker/Entity/TmdbData.php
namespace Harment\XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * كيان بيانات TMDB
 * يخزن معلومات الأفلام والمسلسلات المجلوبة من TMDB API
 *
 * @property int $tmdb_id
 * @property string $type
 * @property string $title
 * @property string $title_ar
 * @property string $overview
 * @property string $overview_ar
 * @property string $poster_path
 * @property string $backdrop_path
 * @property string $release_date
 * @property float $vote_average
 * @property array $cast
 * @property array $crew
 * @property int $fetch_date
 *
 * @property-read string $poster_url
 * @property-read string $backdrop_url
 * @property-read string $display_title
 */
class TmdbData extends Entity
{
    /**
     * تعريف هيكل الكيان
     *
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xbt_tmdb_data';
        $structure->shortName = 'XBTTracker:TmdbData';
        $structure->primaryKey = 'tmdb_id';
        
        $structure->columns = [
            'tmdb_id' => ['type' => self::UINT, 'required' => true],
            'type' => ['type' => self::STR, 'required' => true, 'default' => 'movie'],
            'title' => ['type' => self::STR, 'required' => true],
            'title_ar' => ['type' => self::STR, 'default' => ''],
            'overview' => ['type' => self::STR, 'default' => ''],
            'overview_ar' => ['type' => self::STR, 'default' => ''],
            'poster_path' => ['type' => self::STR, 'default' => ''],
            'backdrop_path' => ['type' => self::STR, 'default' => ''],
            'release_date' => ['type' => self::STR, 'default' => ''],
            'vote_average' => ['type' => self::FLOAT, 'default' => 0],
            'cast' => ['type' => self::JSON_ARRAY, 'default' => []],
            'crew' => ['type' => self::JSON_ARRAY, 'default' => []],
            'fetch_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];
        
        $structure->getters = [
            'poster_url' => true,
            'backdrop_url' => true,
            'display_title' => true,
            'cast_array' => true,
            'crew_array' => true
        ];
        
        $structure->relations = [
            'Torrents' => [
                'entity' => 'XBTTracker:Torrent',
                'type' => self::TO_MANY,
                'conditions' => [
                    ['tmdb_id', '=', '$tmdb_id']
                ],
                'key' => 'torrent_id'
            ]
        ];
        
        return $structure;
    }
    
    /**
     * الحصول على رابط صورة الملصق (بوستر)
     *
     * @return string
     */
    public function getPosterUrl()
    {
        if (!$this->poster_path) {
            return '';
        }
        
        return 'https://image.tmdb.org/t/p/w500' . $this->poster_path;
    }
    
    /**
     * الحصول على رابط صورة الخلفية
     *
     * @return string
     */
    public function getBackdropUrl()
    {
        if (!$this->backdrop_path) {
            return '';
        }
        
        return 'https://image.tmdb.org/t/p/original' . $this->backdrop_path;
    }
    
    /**
     * الحصول على العنوان المناسب حسب اللغة الحالية
     *
     * @return string
     */
    public function getDisplayTitle()
    {
        $language = \XF::language()->getLanguageCode();
        
        if ($language == 'ar' && $this->title_ar) {
            return $this->title_ar;
        }
        
        return $this->title;
    }
    
    /**
     * الحصول على مصفوفة الممثلين
     * دالة للتوافق مع الكود القديم
     *
     * @return array
     */
    public function getCastArray()
    {
        return $this->cast;
    }
    
    /**
     * الحصول على مصفوفة طاقم العمل
     * دالة للتوافق مع الكود القديم
     *
     * @return array
     */
    public function getCrewArray()
    {
        return $this->crew;
    }
    
    /**
     * فحص ما إذا كانت البيانات قديمة وتحتاج للتحديث
     *
     * @param int $maxAgeDays العمر الأقصى بالأيام
     * @return bool
     */
    public function isStale($maxAgeDays = 7)
    {
        return ($this->fetch_date < (\XF::$time - 86400 * $maxAgeDays));
    }
}