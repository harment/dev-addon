<?php

namespace Harment\XBTTracker\Service\Torrent;

use XF\Service\AbstractService;
use XF\Http\Upload;
use XF\Util\File;

class Creator extends AbstractService
{
    /**
     * @var \Harment\XBTTracker\Entity\Torrent
     */
    protected $torrent;
    
    /**
     * @var Upload
     */
    protected $torrentFile;
    
    /**
     * @var Upload|null
     */
    protected $posterFile;
    
    /**
     * @var array
     */
    protected $torrentData;
    
    /**
     * Constructor
     * 
     * @param \XF\App $app
     */
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        
        $this->torrent = $this->em()->create('Harment\XBTTracker:Torrent');
    }
    
    /**
     * Set torrent file
     * 
     * @param Upload $upload Torrent file
     * @return self
     */
    public function setTorrentFile(Upload $upload)
    {
        $this->torrentFile = $upload;
        
        return $this;
    }
    
    /**
     * Set poster file
     * 
     * @param Upload $upload Poster image file
     * @return self
     */
    public function setPosterFile(Upload $upload)
    {
        $this->posterFile = $upload;
        
        return $this;
    }
    
    /**
     * Set torrent data
     * 
     * @param array $data Torrent metadata
     * @return self
     */
    public function setData(array $data)
    {
        $this->torrentData = $data;
        
        return $this;
    }
    
    /**
     * Validate torrent
     * 
     * @param array $errors Output parameter for validation errors
     * @return bool True if valid, false otherwise
     */
    public function validate(&$errors = [])
    {
        if (!$this->torrentFile) {
            $errors[] = \XF::phrase('xbt_torrent_file_required');
            return false;
        }
        
        // Process the torrent file
        $torrentPath = $this->processTorrentFile($errors);
        if (!$torrentPath) {
            return false;
        }
        
        // Process poster file if exists
        $posterPath = '';
        if ($this->posterFile) {
            $posterPath = $this->processPosterFile($errors);
            if (!$posterPath && $errors) {
                return false;
            }
        }
        
        // Set up torrent entity
        $visitor = \XF::visitor();
        
        $this->torrent->bulkSet($this->torrentData);
        $this->torrent->file_path = $torrentPath;
        
        if ($posterPath) {
            $this->torrent->poster_path = $posterPath;
        }
        
        // Validate the entity
        $errors = $this->torrent->getErrors();
        
        return count($errors) == 0;
    }
    
    /**
     * Save torrent
     * 
     * @return \Harment\XBTTracker\Entity\Torrent|false The saved entity or false on failure
     */
    public function save()
    {
        if ($this->torrent->save()) {
            return $this->torrent;
        }
        
        return false;
    }
    
    /**
     * Process torrent file
     * 
     * @param array $errors Output parameter for validation errors
     * @return string|false The file path if successful, false otherwise
     */
    protected function processTorrentFile(&$errors = [])
    {
        $torrentFile = $this->torrentFile;
        
        if (!$torrentFile->isValid()) {
            $errors[] = \XF::phrase('xbt_torrent_file_invalid');
            return false;
        }
        
        // Validate file extension
        $fileName = $torrentFile->getFileName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext != 'torrent') {
            $errors[] = \XF::phrase('xbt_invalid_torrent_file_extension');
            return false;
        }
        
        // Parse the torrent file
        $torrentData = $this->parseTorrentFile($torrentFile->getTempFile(), $errors);
        if (!$torrentData) {
            return false;
        }
        
        // Get info hash
        $infoHash = $torrentData['info_hash'];
        
        // Check if torrent exists
        $existingTorrent = $this->finder('Harment\XBTTracker:Torrent')
            ->where('info_hash', $infoHash)
            ->fetchOne();
            
        if ($existingTorrent) {
            $errors[] = \XF::phrase('torrent_with_same_info_hash_already_exists');
            return false;
        }
        
        // Save torrent file
        $torrentPath = $this->options()->xbtTrackerTorrentPath;
        if (!$torrentPath) {
            $torrentPath = 'data/torrents';
        }
        
        $finalFileName = $infoHash . '.torrent';
        $finalPath = $torrentPath . '/' . $finalFileName;
        
        if (!File::copyFileToAbstractedPath($torrentFile->getTempFile(), $finalPath)) {
            $errors[] = \XF::phrase('xbt_error_saving_torrent_file');
            return false;
        }
        
        // Set torrent data
        $this->torrent->info_hash = $infoHash;
        $this->torrent->size = $torrentData['size'];
        
        return $finalPath;
    }
    
    /**
     * Process poster file
     * 
     * @param array $errors Output parameter for validation errors
     * @return string|false The file path if successful, false otherwise
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
     * Parse torrent file
     * 
     * @param string $filePath Path to the torrent file
     * @param array $errors Output parameter for validation errors
     * @return array|false Torrent metadata if successful, false otherwise
     */
    protected function parseTorrentFile($filePath, &$errors = [])
    {
        // Load the bencode library (assumed to be available)
        require_once(\XF::getSourceDirectory() . '/addons/Harment/XBTTracker/bencode.php');
        
        // Read and decode the torrent file
        $torrentContent = @file_get_contents($filePath);
        if (!$torrentContent) {
            $errors[] = \XF::phrase('xbt_invalid_torrent_file_format');
            return false;
        }
        
        try {
            $decoded = \Bencode::decode($torrentContent);
        } catch (\Exception $e) {
            $errors[] = \XF::phrase('xbt_invalid_torrent_file_format');
            return false;
        }
        
        // Validate the torrent structure
        if (!isset($decoded['info'])) {
            $errors[] = \XF::phrase('xbt_invalid_torrent_file_missing_info');
            return false;
        }
        
        // Calculate info hash
        $infoSection = \Bencode::encode($decoded['info']);
        $infoHash = strtoupper(bin2hex(sha1($infoSection, true)));
        
        // Calculate size
        $size = 0;
        if (isset($decoded['info']['files']) && is_array($decoded['info']['files'])) {
            // Multiple file torrent
            foreach ($decoded['info']['files'] as $file) {
                if (isset($file['length'])) {
                    $size += intval($file['length']);
                }
            }
        } else if (isset($decoded['info']['length'])) {
            // Single file torrent
            $size = intval($decoded['info']['length']);
        }
        
        return [
            'info_hash' => $infoHash,
            'size' => $size
        ];
    }
}