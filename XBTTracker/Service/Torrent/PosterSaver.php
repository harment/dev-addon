<?php
// src/addons/Harment/XBTTracker/Service/Torrent/PosterSaver.php
namespace Harment\XBTTracker\Service\Torrent;

use XF\Service\AbstractService;
use XF\Http\Upload;
use XF\Util\File;

class PosterSaver extends AbstractService
{
    /**
     * Save poster for a torrent
     *
     * @param \Harment\XBTTracker\Entity\Torrent $torrent
     * @param Upload $posterFile
     * @return bool Success or failure
     */
    public function savePoster(\Harment\XBTTracker\Entity\Torrent $torrent, Upload $posterFile)
    {
        if (!$posterFile->isValid()) {
            return false;
        }
        
        $extension = $posterFile->getExtension();
        $validExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array(strtolower($extension), $validExtensions)) {
            return false;
        }
        
        $path = $this->app->options()->xbtTrackerTorrentPath;
        if (!$path) {
            $path = 'data/torrents';
        }
        
        $posterPath = $path . '/posters';
        
        // Create directory if it doesn't exist
        File::createDirectory($posterPath);
        
        // Generate a unique filename
        $fileName = $this->generatePosterFileName($torrent);
        
        // Save the file
        if (!$posterFile->moveToAbsolutePath($posterPath . '/' . $fileName)) {
            return false;
        }
        
        // Update torrent with poster path
        $torrent->poster_path = $posterPath . '/' . $fileName;
        $torrent->save();
        
        return true;
    }
    
    /**
     * Generate a unique poster filename
     *
     * @param \Harment\XBTTracker\Entity\Torrent $torrent
     * @return string Unique filename
     */
    protected function generatePosterFileName(\Harment\XBTTracker\Entity\Torrent $torrent)
    {
        $fileName = md5(
            $torrent->torrent_id . '_' .
            $torrent->title . '_' .
            microtime() . '_' .
            rand(1, 1000)
        );
        
        return $fileName . '.jpg';
    }
}