<?php

namespace XBTTracker\Service\Torrent;

use XF\Service\AbstractService;
use XF\Http\Upload;
use XF\Util\File;

class Editor extends AbstractService
{
    /**
     * @var \XBTTracker\Entity\Torrent
     */
    protected $torrent;
    
    /**
     * @var Upload
     */
    protected $posterFile;
    
    /**
     * @var array
     */
    protected $torrentData;
    
    /**
     * Constructor
     */
    public function __construct(\XF\App $app, \XBTTracker\Entity\Torrent $torrent)
    {
        parent::__construct($app);
        
        $this->torrent = $torrent;
    }
    
    /**
     * Set poster file
     */
    public function setPosterFile(Upload $upload)
    {
        $this->posterFile = $upload;
        
        return $this;
    }
    
    /**
     * Set torrent data
     */
    public function setData(array $data)
    {
        $this->torrentData = $data;
        
        return $this;
    }
    
    /**
     * Validate torrent
     */
    public function validate(&$errors = [])
    {
        // Process poster file if exists
        $posterPath = '';
        if ($this->posterFile) {
            $posterPath = $this->processPosterFile($errors);
            if (!$posterPath && $errors) {
                return false;
            }
        }
        
        // Set up torrent entity
        $this->torrent->bulkSet($this->torrentData);
        
        if ($posterPath) {
            // Delete old poster if exists
            $this->deleteOldPoster();
            
            // Set new poster path
            $this->torrent->poster_path = $posterPath;
        }
        
        // Validate the entity
        $errors = $this->torrent->getErrors();
        
        return count($errors) == 0;
    }
    
    /**
     * Save torrent
     */
    public function save()
    {
        if ($this->torrent->save()) {
            return $this->torrent;
        }
        
        return false;
    }
    
    /**
     * Process poster file
     */
    protected function processPosterFile(&$errors = [])
    {
        $posterFile = $this->posterFile;
        
        if (!$posterFile->isValid()) {
            $errors[] = \XF::phrase('xbt_poster_file_invalid');
            return false;
        }
        
        // Validate file extension
        $fileName = $posterFile->getFileName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $validExts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($ext, $validExts)) {
            $errors[] = \XF::phrase('xbt_invalid_poster_file_extension');
            return false;
        }
        
        // Save poster file
        $torrentPath = $this->options()->xbtTrackerTorrentPath;
        if (!$torrentPath) {
            $torrentPath = 'data/torrents';
        }
        
        $posterPath = $torrentPath . '/posters';
        $finalFileName = \XF::$time . '_' . \XF::generateRandomString(8) . '.' . $ext;
        $finalPath = $posterPath . '/' . $finalFileName;
        
        if (!File::copyFileToAbstractedPath($posterFile->getTempFile(), $finalPath)) {
            $errors[] = \XF::phrase('xbt_error_saving_poster_file');
            return false;
        }
        
        return $finalPath;
    }
    
    /**
     * Delete old poster
     */
    protected function deleteOldPoster()
    {
        $posterPath = $this->torrent->poster_path;
        
        if ($posterPath && file_exists($posterPath)) {
            File::deleteFromAbstractedPath($posterPath);
        }
    }
}