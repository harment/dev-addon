<?php
// src/addons/XBTTracker/Entity/TmdbData.php
namespace XBTTracker\Entity;

use XF\Mvc\Entity\Structure;

class TmdbData extends \XF\Mvc\Entity\Entity
{
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
            'display_title' => true
        ];
        
        return $structure;
    }
    
    /**
     * Get poster URL
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
     * Get backdrop URL
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
     * Get the appropriate title based on current language
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
}
