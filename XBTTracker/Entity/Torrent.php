<?php
// src/addons/XBTTracker/Entity/Torrent.php
namespace XBTTracker\Entity;

use XF\Mvc\Entity\Structure;

class Torrent extends \XF\Mvc\Entity\Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xbt_torrents';
        $structure->shortName = 'XBTTracker:Torrent';
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
            'poster_url' => true,
            'video_quality_badge' => true,
            'audio_format_badge' => true,
            'audio_channels_badge' => true,
            'size_formatted' => true,
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
            ]
        ];
        
        return $structure;
    }
    
    /**
     * Get URL parameters for this torrent
     *
     * @return array
     */
    public function getUrlParams()
    {
        return [
            'torrent_id' => $this->torrent_id,
            'title' => \XF::app()->stringFormatter->wholeWordTrim($this->title, 30, 0, '')
        ];
    }
    
    /**
     * Get torrent ratio (seeders/leechers)
     *
     * @return float
     */
    public function getRatio()
    {
        if ($this->leechers == 0) {
            return $this->seeders > 0 ? 999 : 0;
        }
        
        return round($this->seeders / $this->leechers, 2);
    }
    
    /**
     * Get formatted size
     *
     * @return string
     */
    public function getSizeFormatted()
    {
        return \XF::language()->fileSizeFormat($this->size);
    }
    
    /**
     * Get poster URL
     *
     * @return string
     */
    public function getPosterUrl()
    {
        if (!$this->poster_path) {
            if ($this->tmdb_id && $this->TmdbData && $this->TmdbData->poster_path) {
                return $this->TmdbData->poster_url;
            }
            
            // Return default poster
            return \XF::app()->templater()->getTemplateUrl('public:xbt_default_poster.png');
        }
        
        return \XF::app()->router()->buildLink('full:torrents/poster', $this, [
            'hash' => md5($this->poster_path . $this->torrent_id)
        ]);
    }
    
    /**
     * Get video quality badge
     *
     * @return array
     */
    public function getVideoQualityBadge()
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
     * Get audio format badge
     *
     * @return array
     */
    public function getAudioFormatBadge()
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
     * Get audio channels badge
     *
     * @return array
     */
    public function getAudioChannelsBadge()
    {
        $channels = $this->audio_channels;
        
        return [
            'icon' => 'fa-volume-up',
            'text' => $channels,
            'class' => 'audioChannels--' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $channels))
        ];
    }
}