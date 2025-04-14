<?php
// src/addons/XBTTracker/Service/Torrent/Uploader.php
namespace XBTTracker\Service\Torrent;

use XF\Service\AbstractService;

class Uploader extends AbstractService
{
    /**
     * @var int
     */
    protected $userId;
    
    /**
     * @var \XF\Http\Upload
     */
    protected $torrentFile;
    
    /**
     * @var \XF\Http\Upload|null
     */
    protected $posterFile;
    
    /**
     * @var string
     */
    protected $title;
    
    /**
     * @var string
     */
    protected $description;
    
    /**
     * @var int
     */
    protected $categoryId;
    
    /**
     * @var string
     */
    protected $videoQuality;
    
    /**
     * @var string
     */
    protected $audioFormat;
    
    /**
     * @var string
     */
    protected $audioChannels;
    
    /**
     * @var int
     */
    protected $tmdbId;
    
    /**
     * @var bool
     */
    protected $freeleech;
    
    /**
     * Set the user ID
     *
     * @param int $userId
     * @return self
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
        return $this;
    }
    
    /**
     * Set the torrent file
     *
     * @param \XF\Http\Upload $torrentFile
     * @return self
     */
    public function setTorrentFile(\XF\Http\Upload $torrentFile)
    {
        $this->torrentFile = $torrentFile;
        return $this;
    }
    
    /**
     * Set the poster file
     *
     * @param \XF\Http\Upload|null $posterFile
     * @return self
     */
    public function setPosterFile(\XF\Http\Upload $posterFile = null)
    {
        $this->posterFile = $posterFile;
        return $this;
    }
    
    /**
     * Set the title
     *
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }
    
    /**
     * Set the description
     *
     * @param string $description
     * @return self
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }
    
    /**
     * Set the category ID
     *
     * @param int $categoryId
     * @return self
     */
    public function setCategoryId($categoryId)
    {
        $this->categoryId = $categoryId;
        return $this;
    }
    
    /**
     * Set the video quality
     *
     * @param string $videoQuality
     * @return self
     */
    public function setVideoQuality($videoQuality)
    {
        $this->videoQuality = $videoQuality;
        return $this;
    }
    
    /**
     * Set the audio format
     *
     * @param string $audioFormat
     * @return self
     */
    public function setAudioFormat($audioFormat)
    {
        $this->audioFormat = $audioFormat;
        return $this;
    }
    
    /**
     * Set the audio channels
     *
     * @param string $audioChannels
     * @return self
     */
    public function setAudioChannels($audioChannels)
    {
        $this->audioChannels = $audioChannels;
        return $this;
    }
    
    /**
     * Set the TMDB ID
     *
     * @param int $tmdbId
     * @return self
     */
    public function setTmdbId($tmdbId)
    {
        $this->tmdbId = $tmdbId;
        return $this;
    }
    
    /**
     * Set whether this torrent is freeleech
     *
     * @param bool $freeleech
     * @return self
     */
    public function setFreeleech($freeleech)
    {
        $this->freeleech = $freeleech;
        return $this;
    }
    
    /**
     * Upload the torrent
     *
     * @return \XBTTracker\Entity\Torrent|string
     */
    public function upload()
    {
        $torrentValidator = $this->validateTorrentFile();
        if ($torrentValidator !== true) {
            return $torrentValidator;
        }
        
        if ($this->posterFile) {
            $posterValidator = $this->validatePosterFile();
            if ($posterValidator !== true) {
                return $posterValidator;
            }
        }
        
        if (!$this->title) {
            return \XF::phrase('xbt_torrent_title_required');
        }
        
        if (!$this->categoryId) {
            return \XF::phrase('xbt_torrent_category_required');
        }
        
        // Create new torrent entity
        /** @var \XBTTracker\Entity\Torrent $torrent */
        $torrent = $this->em()->create('XBTTracker:Torrent');
        $torrent->title = $this->title;
        $torrent->description = $this->description;
        $torrent->user_id = $this->userId;
        $torrent->category_id = $this->categoryId;
        $torrent->video_quality = $this->videoQuality;
        $torrent->audio_format = $this->audioFormat;
        $torrent->audio_channels = $this->audioChannels;
        $torrent->tmdb_id = $this->tmdbId;
        $torrent->is_freeleech = $this->freeleech;
        $torrent->creation_date = \XF::$time;
        
        // Save torrent file
        $torrentFilePath = $this->saveTorrentFile();
        if (!$torrentFilePath) {
            return \XF::phrase('xbt_error_saving_torrent_file');
        }
        
        $torrent->file_path = $torrentFilePath;
        
        // Parse .torrent file and get info hash
        $torrentData = \XBTTracker\Util\Bencode::decode(file_get_contents($torrentFilePath));
        if (isset($torrentData['info'])) {
            $infoSection = \XBTTracker\Util\Bencode::encode($torrentData['info']);
            $infoHash = strtolower(bin2hex(sha1($infoSection, true)));
            $torrent->info_hash = $infoHash;
            
            // Calculate size
            if (isset($torrentData['info']['length'])) {
                $torrent->size = $torrentData['info']['length'];
            } else if (isset($torrentData['info']['files'])) {
                $size = 0;
                foreach ($torrentData['info']['files'] as $file) {
                    $size += $file['length'];
                }
                $torrent->size = $size;
            }
        } else {
            return \XF::phrase('xbt_invalid_torrent_file_missing_info');
        }
        
        // Save poster file if provided
        if ($this->posterFile) {
            /** @var PosterSaver $posterSaver */
            $posterSaver = $this->service('XBTTracker:Torrent\PosterSaver');
            $posterSaver->savePoster($torrent, $this->posterFile);
        }
        
        // If TMDB ID is provided, fetch and store metadata
        if ($this->tmdbId) {
            $this->fetchTmdbMetadata($torrent);
        }
        
        $torrent->save();
        
        // Announce to tracker
        $this->announceToTracker($torrent);
        
        return $torrent;
    }
    
    /**
     * Validate the uploaded torrent file
     *
     * @return bool|string
     */
    protected function validateTorrentFile()
    {
        if (!$this->torrentFile->isValid()) {
            return \XF::phrase('xbt_torrent_file_invalid');
        }
        
        $extension = $this->torrentFile->getExtension();
        if (strtolower($extension) != 'torrent') {
            return \XF::phrase('xbt_invalid_torrent_file_extension');
        }
        
        // Try to decode the torrent file to ensure it's valid
        $tempPath = $this->torrentFile->getTempFile();
        $torrentData = \XBTTracker\Util\Bencode::decode(file_get_contents($tempPath));
        
        if (!$torrentData) {
            return \XF::phrase('xbt_invalid_torrent_file_format');
        }
        
        if (!isset($torrentData['info'])) {
            return \XF::phrase('xbt_invalid_torrent_file_missing_info');
        }
        
        return true;
    }
    
    /**
     * Validate the uploaded poster file
     *
     * @return bool|string
     */
    protected function validatePosterFile()
    {
        if (!$this->posterFile->isValid()) {
            return \XF::phrase('xbt_poster_file_invalid');
        }
        
        $extension = $this->posterFile->getExtension();
        $validExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array(strtolower($extension), $validExtensions)) {
            return \XF::phrase('xbt_invalid_poster_file_extension');
        }
        
        return true;
    }
    
    /**
     * Save the torrent file to disk
     *
     * @return string|false
     */
    protected function saveTorrentFile()
    {
        $path = $this->app->options()->xbtTrackerTorrentPath;
        if (!$path) {
            $path = 'data/torrents';
        }
        
        // Create directory if it doesn't exist
        \XF\Util\File::createDirectory($path);
        
        // Generate a unique filename
        $fileName = $this->generateTorrentFileName();
        
        // Save the file
        $this->torrentFile->moveToAbsolutePath($path . '/' . $fileName);
        
        return $path . '/' . $fileName;
    }
    
    /**
     * Generate a unique torrent filename
     *
     * @return string
     */
    protected function generateTorrentFileName()
    {
        $fileName = md5(
            $this->userId . '_' .
            $this->title . '_' .
            microtime() . '_' .
            rand(1, 1000)
        ) . '.torrent';
        
        return $fileName;
    }
    
    /**
     * Fetch metadata from TMDB
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     */
    protected function fetchTmdbMetadata(\XBTTracker\Entity\Torrent $torrent)
    {
        // Check if we already have this TMDB data
        $tmdbData = $this->finder('XBTTracker:TmdbData')
            ->where('tmdb_id', $this->tmdbId)
            ->fetchOne();
            
        if (!$tmdbData) {
            /** @var \XBTTracker\Service\Tmdb\Client $tmdbClient */
            $tmdbClient = $this->service('XBTTracker:Tmdb\Client');
            $tmdbInfo = $tmdbClient->getDetails($this->tmdbId);
            
            if ($tmdbInfo) {
                // Create new TMDB data entity
                /** @var \XBTTracker\Entity\TmdbData $tmdbData */
                $tmdbData = $this->em()->create('XBTTracker:TmdbData');
                $tmdbData->tmdb_id = $this->tmdbId;
                $tmdbData->type = isset($tmdbInfo['media_type']) ? $tmdbInfo['media_type'] : 'movie';
                $tmdbData->title = isset($tmdbInfo['title']) ? $tmdbInfo['title'] : (isset($tmdbInfo['name']) ? $tmdbInfo['name'] : '');
                $tmdbData->overview = isset($tmdbInfo['overview']) ? $tmdbInfo['overview'] : '';
                $tmdbData->poster_path = isset($tmdbInfo['poster_path']) ? $tmdbInfo['poster_path'] : '';
                $tmdbData->backdrop_path = isset($tmdbInfo['backdrop_path']) ? $tmdbInfo['backdrop_path'] : '';
                $tmdbData->release_date = isset($tmdbInfo['release_date']) ? $tmdbInfo['release_date'] : (isset($tmdbInfo['first_air_date']) ? $tmdbInfo['first_air_date'] : '');
                $tmdbData->vote_average = isset($tmdbInfo['vote_average']) ? $tmdbInfo['vote_average'] : 0;
                
                // Get Arabic translation if available
                $tmdbClient->getTranslation($tmdbData);
                
                // Get cast and crew
                $credits = $tmdbClient->getCredits($this->tmdbId, $tmdbData->type);
                if ($credits) {
                    $tmdbData->cast = isset($credits['cast']) ? array_slice($credits['cast'], 0, 10) : [];
                    $tmdbData->crew = isset($credits['crew']) ? array_slice($credits['crew'], 0, 10) : [];
                }
                
                $tmdbData->save();
            }
        }
    }
    
    /**
     * Announce a new torrent to the XBT tracker
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     */
    protected function announceToTracker(\XBTTracker\Entity\Torrent $torrent)
    {
        // This would be implemented when integrating with the XBT tracker directly
        // For now, we're leaving this as a placeholder
    }
}