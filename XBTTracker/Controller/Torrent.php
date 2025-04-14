<?php
// src/addons/XBTTracker/Controller/Torrent.php
namespace XBTTracker\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\Error;

class Torrent extends \XF\Mvc\Controller
{
    /**
     * Display the torrent listing page
     *
     * @return View
     */
    public function actionIndex()
    {
        $this->assertValidPage();
        
        $page = $this->filterPage();
        $perPage = 20;
        
        $categoryId = $this->filter('category_id', 'uint');
        $search = $this->filter('search', 'str');
        $quality = $this->filter('quality', 'str');
        $audio = $this->filter('audio', 'str');
        $channels = $this->filter('channels', 'str');
        $status = $this->filter('status', 'str');
        $sort = $this->filter('sort', 'str', 'date');
        $order = $this->filter('order', 'str', 'desc');
        
        $finder = $this->getTorrentRepo()->findTorrentsForList();
        
        if ($categoryId) {
            $finder->where('category_id', $categoryId);
        }
        
        if ($search) {
            $finder->where('title', 'LIKE', '%' . $finder->escapeLike($search) . '%');
        }
        
        if ($quality) {
            $finder->where('video_quality', $quality);
        }
        
        if ($audio) {
            $finder->where('audio_format', $audio);
        }
        
        if ($channels) {
            $finder->where('audio_channels', $channels);
        }
        
        if ($status == 'active') {
            $finder->where('seeders', '>', 0);
        } else if ($status == 'dead') {
            $finder->where('seeders', 0);
        }
        
        switch ($sort) {
            case 'seeds':
                $finder->order('seeders', $order);
                break;
            case 'size':
                $finder->order('size', $order);
                break;
            case 'completed':
                $finder->order('completed', $order);
                break;
            case 'title':
                $finder->order('title', $order);
                break;
            case 'date':
            default:
                $finder->order('creation_date', $order);
        }
        
        $finder->limitByPage($page, $perPage);
        
        $categories = $this->finder('XBTTracker:Category')
            ->order('display_order')
            ->fetch();
            
        $trackerStats = $this->getTorrentRepo()->getTorrentStats();
        
        $viewParams = [
            'torrents' => $finder->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $finder->total(),
            'latestTorrents' => $this->getTorrentRepo()->findLatestTorrents(20)->fetch(),
            'categories' => $categories,
            'categoryId' => $categoryId,
            'search' => $search,
            'quality' => $quality,
            'audio' => $audio,
            'channels' => $channels,
            'status' => $status,
            'sort' => $sort,
            'order' => $order,
            'videoQualities' => $this->getVideoQualityOptions(),
            'audioFormats' => $this->getAudioFormatOptions(),
            'audioChannels' => $this->getAudioChannelOptions(),
            'trackerStats' => $trackerStats
        ];
        
        return $this->view('XBTTracker:Torrent\Listing', 'xbt_torrent_list', $viewParams);
    }
    
    /**
     * View a specific torrent
     *
     * @param ParameterBag $params
     * @return View|Error
     */
    public function actionView(ParameterBag $params)
    {
        $torrentId = $params->torrent_id;
        $torrent = $this->assertTorrentExists($torrentId);
        
        if ($this->filter('download', 'bool')) {
            return $this->actionDownload($torrent);
        }
        
        // Increment view count
        $torrent->view_count++;
        $torrent->save();
        
        $userStats = $this->getUserStats();
        $canDownload = $this->canDownloadTorrent($torrent);
        $downloadReasons = [];
        
        if (!$canDownload) {
            if (!$this->visitor->hasPermission('xbtTracker', 'download')) {
                $downloadReasons[] = \XF::phrase('no_permission');
            }
            
            if ($userStats && !$torrent->is_freeleech && !$this->options()->xbtTrackerGlobalFreeleech) {
                $requiredRatio = $this->options()->xbtTrackerRequiredRatio;
                if ($requiredRatio > 0 && $userStats->ratio < $requiredRatio) {
                    // Check for exemption
                    $exemptGroups = $this->options()->xbtTrackerRatioExemptGroups;
                    if (!$exemptGroups || !array_intersect($this->visitor->secondary_group_ids, $exemptGroups)) {
                        $downloadReasons[] = \XF::phrase('xbt_ratio_too_low');
                    }
                }
            }
        }
        
        $viewParams = [
            'torrent' => $torrent,
            'canDownload' => $canDownload,
            'downloadReasons' => $downloadReasons,
            'userStats' => $userStats,
            'thankedUsers' => [],  // Would need to implement thanks system
            'thanked' => false
        ];
        
        return $this->view('XBTTracker:Torrent\View', 'xbt_torrent_view', $viewParams);
    }
    
    /**
     * Download torrent file
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionDownload(\XBTTracker\Entity\Torrent $torrent)
    {
        if (!$this->canDownloadTorrent($torrent)) {
            return $this->noPermission();
        }
        
        // Check if thank is required
        if ($this->options()->xbtTrackerForceThankYou) {
            // Would need to implement thanks system check here
        }
        
        // Generate passkey URL for this specific user if needed
        $userId = \XF::visitor()->user_id;
        $userStats = $this->getUserStats();
        
        if (!$userStats->passkey) {
            $userStats->generatePasskey();
            $userStats->save();
        }
        
        // Inject passkey into torrent file
        $torrentFilePath = $torrent->file_path;
        $torrentContent = file_get_contents($torrentFilePath);
        
        // Parse and modify torrent announce URL to include passkey
        $torrentData = \XBTTracker\Util\Bencode::decode($torrentContent);
        
        if (isset($torrentData['announce'])) {
            $announceUrl = $torrentData['announce'];
            $separator = strpos($announceUrl, '?') !== false ? '&' : '?';
            $torrentData['announce'] = $announceUrl . $separator . 'passkey=' . $userStats->passkey;
        }
        
        $modifiedTorrentContent = \XBTTracker\Util\Bencode::encode($torrentData);
        
        // Send file to client
        return $this->plugin('XF:Download')->actionDownload(
            $modifiedTorrentContent,
            $torrent->title . '.torrent',
            'application/x-bittorrent',
            true
        );
    }
    
    /**
     * Show the torrent upload form
     *
     * @return View|Error|Redirect
     */
    public function actionUpload()
    {
        $this->assertRegisteredUser();
        
        if (!$this->canUploadTorrent()) {
            return $this->noPermission();
        }
        
        if ($this->isPost()) {
            /** @var \XBTTracker\Service\Torrent\Uploader $torrentUploader */
            $torrentUploader = $this->service('XBTTracker:Torrent\Uploader');
            
            $torrentFile = $this->request->getFile('torrent_file');
            if (!$torrentFile) {
                return $this->error(\XF::phrase('xbt_torrent_file_required'));
            }
            
            $posterFile = $this->request->getFile('poster_file');
            
            $title = $this->filter('title', 'str');
            $description = $this->filter('description', 'str');
            $categoryId = $this->filter('category_id', 'uint');
            $videoQuality = $this->filter('video_quality', 'str');
            $audioFormat = $this->filter('audio_format', 'str');
            $audioChannels = $this->filter('audio_channels', 'str');
            $tmdbId = $this->filter('tmdb_id', 'uint');
            $isFreeleech = $this->filter('is_freeleech', 'bool');
            
            // Only allow freeleech if user has permission
            if ($isFreeleech && !$this->visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
                $isFreeleech = false;
            }
            
            $torrentUploader->setUserId(\XF::visitor()->user_id);
            $torrentUploader->setTorrentFile($torrentFile);
            $torrentUploader->setPosterFile($posterFile);
            $torrentUploader->setTitle($title);
            $torrentUploader->setDescription($description);
            $torrentUploader->setCategoryId($categoryId);
            $torrentUploader->setVideoQuality($videoQuality);
            $torrentUploader->setAudioFormat($audioFormat);
            $torrentUploader->setAudioChannels($audioChannels);
            $torrentUploader->setTmdbId($tmdbId);
            $torrentUploader->setFreeleech($isFreeleech);
            
            $result = $torrentUploader->upload();
            
            if ($result instanceof \XF\Mvc\Entity\Entity) {
                return $this->redirect($this->buildLink('torrents', $result));
            } else {
                return $this->error($result);
            }
        }
        
        $categories = $this->finder('XBTTracker:Category')
            ->order('display_order')
            ->fetch();
        
        $viewParams = [
            'categories' => $categories,
            'videoQualities' => $this->getVideoQualityOptions(),
            'audioFormats' => $this->getAudioFormatOptions(),
            'audioChannels' => $this->getAudioChannelOptions()
        ];
        
        return $this->view('XBTTracker:Torrent\Upload', 'xbt_torrent_upload', $viewParams);
    }
    
    /**
     * Search for a movie/tv show in TMDB
     *
     * @return View|Error
     */
    public function actionTmdbSearch()
    {
        $this->assertRegisteredUser();
        
        $query = $this->filter('query', 'str');
        $type = $this->filter('type', 'str', 'movie');
        
        if (!$query) {
            return $this->error(\XF::phrase('xbt_tmdb_search_query_required'));
        }
        
        /** @var \XBTTracker\Service\Tmdb\Client $tmdbService */
        $tmdbService = $this->service('XBTTracker:Tmdb\Client');
        $results = $tmdbService->search($query, $type);
        
        return $this->view('XBTTracker:Torrent\TmdbSearch', 'xbt_tmdb_search_results', [
            'results' => $results,
            'query' => $query,
            'type' => $type
        ]);
    }
    
    /**
     * Get detailed information about a TMDB item
     *
     * @return View|Error
     */
    public function actionTmdbInfo()
    {
        $this->assertRegisteredUser();
        
        $tmdbId = $this->filter('tmdb_id', 'uint');
        $type = $this->filter('type', 'str', 'movie');
        
        if (!$tmdbId) {
            return $this->error(\XF::phrase('xbt_tmdb_id_required'));
        }
        
        /** @var \XBTTracker\Service\Tmdb\Client $tmdbService */
        $tmdbService = $this->service('XBTTracker:Tmdb\Client');
        $info = $tmdbService->getDetails($tmdbId, $type);
        
        return $this->view('XBTTracker:Torrent\TmdbInfo', 'xbt_tmdb_info', [
            'info' => $info,
            'type' => $type
        ]);
    }
    
    /**
     * Serve torrent poster image
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionPoster(ParameterBag $params)
    {
        $torrentId = $params->torrent_id;
        $hash = $this->filter('hash', 'str');
        
        $torrent = $this->assertTorrentExists($torrentId);
        
        if (!$torrent->poster_path || $hash != md5($torrent->poster_path . $torrent->torrent_id)) {
            return $this->notFound();
        }
        
        $posterPath = $torrent->poster_path;
        
        if (!file_exists($posterPath)) {
            return $this->notFound();
        }
        
        $imageInfo = getimagesize($posterPath);
        $mimeType = $imageInfo['mime'];
        
        $this->setResponseType('raw');
        
        $stream = new \XF\Http\Stream\File($posterPath);
        $response = $this->response();
        $response->header('Content-Type', $mimeType);
        $response->header('Content-Length', filesize($posterPath));
        $response->body($stream);
        
        return $response;
    }
    
    /**
     * Torrent editing form
     *
     * @param ParameterBag $params
     * @return View|Error|Redirect
     */
    public function actionEdit(ParameterBag $params)
    {
        $torrent = $this->assertTorrentExists($params->torrent_id);
        
        if (!$this->canEditTorrent($torrent)) {
            return $this->noPermission();
        }
        
        if ($this->isPost()) {
            $title = $this->filter('title', 'str');
            $description = $this->filter('description', 'str');
            $categoryId = $this->filter('category_id', 'uint');
            $videoQuality = $this->filter('video_quality', 'str');
            $audioFormat = $this->filter('audio_format', 'str');
            $audioChannels = $this->filter('audio_channels', 'str');
            $tmdbId = $this->filter('tmdb_id', 'uint');
            $isFreeleech = $this->filter('is_freeleech', 'bool');
            
            // Only allow freeleech if user has permission
            if ($isFreeleech && !$this->visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
                $isFreeleech = false;
            }
            
            $torrent->title = $title;
            $torrent->description = $description;
            $torrent->category_id = $categoryId;
            $torrent->video_quality = $videoQuality;
            $torrent->audio_format = $audioFormat;
            $torrent->audio_channels = $audioChannels;
            $torrent->tmdb_id = $tmdbId;
            $torrent->is_freeleech = $isFreeleech;
            
            $posterFile = $this->request->getFile('poster_file');
            if ($posterFile && $posterFile->isValid()) {
                /** @var \XBTTracker\Service\Torrent\PosterSaver $posterSaver */
                $posterSaver = $this->service('XBTTracker:Torrent\PosterSaver');
                $posterSaver->savePoster($torrent, $posterFile);
            }
            
            $torrent->save();
            
            return $this->redirect($this->buildLink('torrents', $torrent));
        }
        
        $categories = $this->finder('XBTTracker:Category')
            ->order('display_order')
            ->fetch();
        
        $viewParams = [
            'torrent' => $torrent,
            'categories' => $categories,
            'videoQualities' => $this->getVideoQualityOptions(),
            'audioFormats' => $this->getAudioFormatOptions(),
            'audioChannels' => $this->getAudioChannelOptions()
        ];
        
        return $this->view('XBTTracker:Torrent\Edit', 'xbt_torrent_edit', $viewParams);
    }
    
    /**
     * Delete a torrent
     *
     * @param ParameterBag $params
     * @return View|Error|Redirect
     */
    public function actionDelete(ParameterBag $params)
    {
        $torrent = $this->assertTorrentExists($params->torrent_id);
        
        if (!$this->canDeleteTorrent($torrent)) {
            return $this->noPermission();
        }
        
        if ($this->isPost()) {
            $torrent->delete();
            
            return $this->redirect($this->buildLink('torrents'));
        }
        
        $viewParams = [
            'torrent' => $torrent
        ];
        
        return $this->view('XBTTracker:Torrent\Delete', 'xbt_torrent_delete', $viewParams);
    }
    
    /**
     * Assert that a torrent exists
     *
     * @param int $id
     * @param array|string|null $with
     * @return \XBTTracker\Entity\Torrent
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertTorrentExists($id, $with = null)
    {
        if ($with === null) {
            $with = ['User', 'Category', 'TmdbData'];
        }
        
        $torrent = $this->finder('XBTTracker:Torrent')
            ->with($with)
            ->where('torrent_id', $id)
            ->fetchOne();
            
        if (!$torrent) {
            throw $this->exception($this->notFound(\XF::phrase('xbt_requested_torrent_not_found')));
        }
        
        return $torrent;
    }
    
    /**
     * Check if user can download a torrent
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool
     */
    protected function canDownloadTorrent(\XBTTracker\Entity\Torrent $torrent)
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->user_id) {
            return false;
        }
        
        if (!$visitor->hasPermission('xbtTracker', 'download')) {
            return false;
        }
        
        // If global freeleech is enabled, no ratio check needed
        if ($this->options()->xbtTrackerGlobalFreeleech) {
            return true;
        }
        
        // If this torrent is freeleech, no ratio check needed
        if ($torrent->is_freeleech) {
            return true;
        }
        
        $userStats = $this->getUserStats();
        
        // Check ratio requirement
        $requiredRatio = $this->options()->xbtTrackerRequiredRatio;
        
        if ($requiredRatio > 0 && $userStats->ratio < $requiredRatio) {
            // Group exceptions
            $exemptGroups = $this->options()->xbtTrackerRatioExemptGroups;
            if (!$exemptGroups || !array_intersect($visitor->secondary_group_ids, $exemptGroups)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if user can upload a torrent
     *
     * @return bool
     */
    protected function canUploadTorrent()
    {
        return \XF::visitor()->hasPermission('xbtTracker', 'upload');
    }
    
    /**
     * Check if user can edit a torrent
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool
     */
    protected function canEditTorrent(\XBTTracker\Entity\Torrent $torrent)
    {
        $visitor = \XF::visitor();
        
        if ($visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
            return true;
        }
        
        if ($torrent->user_id == $visitor->user_id && $visitor->hasPermission('xbtTracker', 'edit')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user can delete a torrent
     *
     * @param \XBTTracker\Entity\Torrent $torrent
     * @return bool
     */
    protected function canDeleteTorrent(\XBTTracker\Entity\Torrent $torrent)
    {
        $visitor = \XF::visitor();
        
        if ($visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
            return true;
        }
        
        if ($torrent->user_id == $visitor->user_id && $visitor->hasPermission('xbtTracker', 'delete')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user stats entity
     *
     * @return \XBTTracker\Entity\UserStats|null
     */
    protected function getUserStats()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->user_id) {
            return null;
        }
        
        $stats = $this->finder('XBTTracker:UserStats')
            ->where('user_id', $visitor->user_id)
            ->fetchOne();
            
        if (!$stats) {
            $stats = $this->em()->create('XBTTracker:UserStats');
            $stats->user_id = $visitor->user_id;
            $stats->save();
        }
        
        return $stats;
    }
    
    /**
     * Get torrent repository
     *
     * @return \XBTTracker\Repository\Torrent
     */
    protected function getTorrentRepo()
    {
        return $this->repository('XBTTracker:Torrent');
    }
    
    /**
     * Get video quality options
     *
     * @return array
     */
    protected function getVideoQualityOptions()
    {
        return [
            'DVBTV' => 'DVBTV',
            'DVD' => 'DVD',
            '1080p' => '1080p',
            '4K' => '4K',
            '720p' => '720p',
            'SD' => 'SD',
            'HD' => 'HD',
            'Bluray' => 'Bluray',
            'Remux' => 'Remux'
        ];
    }
    
    /**
     * Get audio format options
     *
     * @return array
     */
    protected function getAudioFormatOptions()
    {
        return [
            'AAC' => 'AAC',
            'AC3' => 'AC3',
            'DTS' => 'DTS',
            'DTS-HD' => 'DTS-HD',
            'Dolby' => 'Dolby'
        ];
    }
    
    /**
     * Get audio channel options
     *
     * @return array
     */
    protected function getAudioChannelOptions()
    {
        return [
            '2.0' => '2.0',
            '5.1' => '5.1',
            '7.2' => '7.2'
        ];
    }
}