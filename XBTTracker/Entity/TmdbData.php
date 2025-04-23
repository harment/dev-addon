<?php
// src/addons/XBTTracker/Entity/TmdbData.php
namespace Harment\XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * TMDB Data Entity
 * Stores movie and TV series information fetched from TMDB API
 * 
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
 * @property-read array $cast_array
 * @property-read array $crew_array
 */
class TmdbData extends Entity
{
    /**
     * Define the entity structure
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
            'type' => ['type' => self::STR, 'required' => true, 'default' => 'movie', 
                       'allowedValues' => ['movie', 'tv']],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 200],
            'title_ar' => ['type' => self::STR, 'default' => '', 'maxLength' => 200],
            'overview' => ['type' => self::STR, 'default' => ''],
            'overview_ar' => ['type' => self::STR, 'default' => ''],
            'poster_path' => ['type' => self::STR, 'default' => ''],
            'backdrop_path' => ['type' => self::STR, 'default' => ''],
            'release_date' => ['type' => self::STR, 'default' => ''],
            'vote_average' => ['type' => self::FLOAT, 'default' => 0, 'min' => 0, 'max' => 10],
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
     * Get the poster image URL
     * الحصول على رابط صورة الملصق (بوستر)
     *
     * @return string
     */
    public function getPosterUrl()
    {
        if (!$this->poster_path) {
            return '';
        }
        
        return $this->getSafeImageUrl('w500', $this->poster_path);
    }
    
    /**
     * Get the backdrop image URL
     * الحصول على رابط صورة الخلفية
     *
     * @return string
     */
    public function getBackdropUrl()
    {
        if (!$this->backdrop_path) {
            return '';
        }
        
        return $this->getSafeImageUrl('original', $this->backdrop_path);
    }
    
    /**
     * Get a safe TMDB image URL with the specified size
     * الحصول على رابط آمن لصور TMDB بالحجم المحدد
     * 
     * @param string $size
     * @param string $path
     * @return string
     */
    protected function getSafeImageUrl($size, $path)
    {
        // Make sure the path is sanitized to prevent any potential security issues
        $path = ltrim(preg_replace('/[^a-zA-Z0-9\-\_\/\.]/', '', $path), '/');
        
        return 'https://image.tmdb.org/t/p/' . $size . '/' . $path;
    }
    
    /**
     * Get the appropriate title based on current language
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
     * Get the cast array
     * Function for compatibility with old code
     * الحصول على مصفوفة الممثلين
     * دالة للتوافق مع الكود القديم
     *
     * @return array
     */
    public function getCastArray()
    {
        return $this->cast ?: [];
    }
    
    /**
     * Get the crew array
     * Function for compatibility with old code
     * الحصول على مصفوفة طاقم العمل
     * دالة للتوافق مع الكود القديم
     *
     * @return array
     */
    public function getCrewArray()
    {
        return $this->crew ?: [];
    }
    
    /**
     * Check if the data is stale and needs updating
     * فحص ما إذا كانت البيانات قديمة وتحتاج للتحديث
     *
     * @param int $maxAgeDays Maximum age in days
     * @return bool
     */
    public function isStale($maxAgeDays = 7)
    {
        return ($this->fetch_date < (\XF::$time - 86400 * $maxAgeDays));
    }
    
    /**
     * Get the release year from the release date
     * الحصول على سنة الإصدار من تاريخ الإصدار
     * 
     * @return string
     */
    public function getReleaseYear()
    {
        if (!$this->release_date || !preg_match('/^(\d{4})/', $this->release_date, $matches)) {
            return '';
        }
        
        return $matches[1];
    }
    
    /**
     * Get the top cast members (limited to a specific count)
     * الحصول على أهم الممثلين (محدود بعدد معين)
     * 
     * @param int $limit
     * @return array
     */
    public function getTopCast($limit = 5)
    {
        $cast = $this->getCastArray();
        return array_slice($cast, 0, $limit);
    }
    
    /**
     * Get the directors from the crew list
     * الحصول على المخرجين من قائمة طاقم العمل
     * 
     * @return array
     */
    public function getDirectors()
    {
        $crew = $this->getCrewArray();
        $directors = [];
        
        foreach ($crew as $member) {
            if (!empty($member['job']) && $member['job'] == 'Director') {
                $directors[] = $member;
            }
        }
        
        return $directors;
    }
    
    /**
     * Get formatted release date
     * الحصول على تاريخ الإصدار بتنسيق معين
     * 
     * @param string $format Date format
     * @return string
     */
    public function getFormattedReleaseDate($format = 'Y-m-d')
    {
        if (!$this->release_date) {
            return '';
        }
        
        $timestamp = strtotime($this->release_date);
        if (!$timestamp) {
            return $this->release_date;
        }
        
        return date($format, $timestamp);
    }
}