<?php
// src/addons/XBTTracker/Service/Torrent/PosterSaver.php
namespace XBTTracker\Service\Torrent;

use XF\Service\AbstractService;

class PosterSaver extends AbstractService
{
    /**
     * Save poster for a torrent
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @param \XF\Http\Upload $posterFile
     * @return bool
     */
    public function savePoster(\XBTTracker\Entity\Torrent $torrent, \XF\Http\Upload $posterFile)
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
        \XF\Util\File::createDirectory($posterPath);
        
        // Generate a unique filename
        $fileName = $this->generatePosterFileName($torrent);
        
        // Save the file
        $posterFile->moveToAbsolutePath($posterPath . '/' . $fileName);
        
        // Update torrent with poster path
        $torrent->poster_path = $posterPath . '/' . $fileName;
        
        return true;
    }
    
    /**
     * Generate a unique poster filename
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return string
     */
    protected function generatePosterFileName(\XBTTracker\Entity\Torrent $torrent)
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



