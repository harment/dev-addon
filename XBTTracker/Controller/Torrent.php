<?php
// src/addons/Harment/XBTTracker/Controller/Torrent.php
namespace Harment\XBTTracker\Controller;

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
        
        $categories = $this->finder('Harment\XBTTracker:Category')
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
        
        return $this->view('Harment\XBTTracker:Torrent\Listing', 'harment_xbttracker_torrent_list', $viewParams);
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
            return $this->actionDownload($params);
        }
        
        // Increment view count
        $torrent->view_count++;
        $torrent->save();
        
        $userStats = $this->getUserStats();
        $canDownload = $this->canDownloadTorrent($torrent);
        $downloadReasons = [];
        
        if (!$canDownload) {
            if (!$this->visitor->hasPermission('harmentXbtTracker', 'download')) {
                $downloadReasons[] = \XF::phrase('no_permission');
            }
            
            if ($userStats && !$torrent->is_freeleech && !$this->options()->harmentXbtTrackerGlobalFreeleech) {
                $requiredRatio = $this->options()->harmentXbtTrackerRequiredRatio;
                if ($requiredRatio > 0 && $userStats->ratio < $requiredRatio) {
                    // Check for exemption
                    $exemptGroups = $this->options()->harmentXbtTrackerRatioExemptGroups;
                    if (!$exemptGroups || !array_intersect($this->visitor->secondary_group_ids, $exemptGroups)) {
                        $downloadReasons[] = \XF::phrase('harment_xbttracker_ratio_too_low');
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
        
        return $this->view('Harment\XBTTracker:Torrent\View', 'harment_xbttracker_torrent_view', $viewParams);
    }
    
    /**
     * Download torrent file
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionDownload(ParameterBag $params)
    {
        $infoHash = $this->filter('info_hash', 'str');
        if (!$infoHash && isset($params['info_hash']))
        {
            $infoHash = $params['info_hash'];
        }
        
        if (!$infoHash)
        {
            return $this->error(\XF::phrase('harment_xbttracker_no_info_hash_provided'));
        }
        
        // التحقق من وجود التورنت
        $torrent = $this->getTorrentByInfoHash($infoHash);
        if (!$torrent)
        {
            return $this->error(\XF::phrase('harment_xbttracker_torrent_not_found'));
        }
        
        // التحقق من صلاحيات المستخدم
        $visitor = \XF::visitor();
        if (!$visitor->user_id && !\XF::options()->harmentXbtTrackerAllowGuestDownloads)
        {
            return $this->noPermission();
        }
        
        // Check ratio requirements if needed
        if ($visitor->user_id && !$this->canDownloadTorrent($torrent)) {
            return $this->noPermission(\XF::phrase('harment_xbttracker_ratio_too_low'));
        }
        
        // تحضير الملف للتحميل
        $filePath = $torrent['file_path'];
        $fileName = $torrent['title'] . '.torrent';
        
        // Generate passkey URL for this specific user if needed
        $userStats = $this->getUserStats();
        
        if ($userStats && !$userStats->passkey) {
            $userStats->generatePasskey();
            $userStats->save();
        }
        
        // تحضير الملف مع رابط التراكر والمفتاح
        $torrentFileContent = $this->prepareTorrentFileForUser($filePath, $visitor);
        
        // إرسال الملف
        return $this->plugin('XF:ControllerPlugin\Attachment')->attachmentFile(
            $torrentFileContent,
            [
                'fileName' => $fileName,
                'fileSize' => strlen($torrentFileContent),
                'contentType' => 'application/x-bittorrent',
                'attachmentType' => 'torrent'
            ]
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
            /** @var \Harment\XBTTracker\Service\Torrent\Uploader $torrentUploader */
            $torrentUploader = $this->service('Harment\XBTTracker:Torrent\Uploader');
            
            $torrentFile = $this->request->getFile('torrent_file');
            if (!$torrentFile) {
                return $this->error(\XF::phrase('harment_xbttracker_torrent_file_required'));
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
            if ($isFreeleech && !$this->visitor->hasPermission('harmentXbtTracker', 'moderateTorrents')) {
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
        
        $categories = $this->finder('Harment\XBTTracker:Category')
            ->order('display_order')
            ->fetch();
        
        $viewParams = [
            'categories' => $categories,
            'videoQualities' => $this->getVideoQualityOptions(),
            'audioFormats' => $this->getAudioFormatOptions(),
            'audioChannels' => $this->getAudioChannelOptions()
        ];
        
        return $this->view('Harment\XBTTracker:Torrent\Upload', 'harment_xbttracker_torrent_upload', $viewParams);
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
            return $this->error(\XF::phrase('harment_xbttracker_tmdb_search_query_required'));
        }
        
        /** @var \Harment\XBTTracker\Service\Tmdb\Client $tmdbService */
        $tmdbService = $this->service('Harment\XBTTracker:Tmdb\Client');
        $results = $tmdbService->search($query, $type);
        
        return $this->view('Harment\XBTTracker:Torrent\TmdbSearch', 'harment_xbttracker_tmdb_search_results', [
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
            return $this->error(\XF::phrase('harment_xbttracker_tmdb_id_required'));
        }
        
        /** @var \Harment\XBTTracker\Service\Tmdb\Client $tmdbService */
        $tmdbService = $this->service('Harment\XBTTracker:Tmdb\Client');
        $info = $tmdbService->getDetails($tmdbId, $type);
        
        return $this->view('Harment\XBTTracker:Torrent\TmdbInfo', 'harment_xbttracker_tmdb_info', [
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
            if ($isFreeleech && !$this->visitor->hasPermission('harmentXbtTracker', 'moderateTorrents')) {
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
                /** @var \Harment\XBTTracker\Service\Torrent\PosterSaver $posterSaver */
                $posterSaver = $this->service('Harment\XBTTracker:Torrent\PosterSaver');
                $posterSaver->savePoster($torrent, $posterFile);
            }
            
            $torrent->save();
            
            return $this->redirect($this->buildLink('torrents', $torrent));
        }
        
        $categories = $this->finder('Harment\XBTTracker:Category')
            ->order('display_order')
            ->fetch();
        
        $viewParams = [
            'torrent' => $torrent,
            'categories' => $categories,
            'videoQualities' => $this->getVideoQualityOptions(),
            'audioFormats' => $this->getAudioFormatOptions(),
            'audioChannels' => $this->getAudioChannelOptions()
        ];
        
        return $this->view('Harment\XBTTracker:Torrent\Edit', 'harment_xbttracker_torrent_edit', $viewParams);
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
        
        return $this->view('Harment\XBTTracker:Torrent\Delete', 'harment_xbttracker_torrent_delete', $viewParams);
    }
    
    /**
     * Assert that a torrent exists
     *
     * @param int $id
     * @param array|string|null $with
     * @return \Harment\XBTTracker\Entity\Torrent
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertTorrentExists($id, $with = null)
    {
        if ($with === null) {
            $with = ['User', 'Category', 'TmdbData'];
        }
        
        $torrent = $this->finder('Harment\XBTTracker:Torrent')
            ->with($with)
            ->where('torrent_id', $id)
            ->fetchOne();
            
        if (!$torrent) {
            throw $this->exception($this->notFound(\XF::phrase('harment_xbttracker_requested_torrent_not_found')));
        }
        
        return $torrent;
    }
    
    /**
     * الحصول على معلومات التورنت من قاعدة البيانات
     *
     * @param string $infoHash
     * @return array|false
     */
    protected function getTorrentByInfoHash($infoHash)
    {
        return \XF::db()->fetchRow('
            SELECT *
            FROM xf_xbt_torrents
            WHERE info_hash = ?
        ', [$infoHash]);
    }
    
    /**
     * تحضير ملف التورنت للمستخدم (إضافة رابط التراكر ومفتاح المستخدم)
     *
     * @param string $filePath
     * @param \XF\Entity\User $user
     * @return string
     */
    protected function prepareTorrentFileForUser($filePath, \XF\Entity\User $user)
    {
        // Get user's passkey
        $userStats = $this->getUserStats();
        $passkey = $userStats ? $userStats->passkey : '';
        
        if (!$passkey) {
            // If no passkey, just return the original file
            return file_get_contents($filePath);
        }
        
        // Attempt to parse and modify the torrent file
        try {
            $torrentContent = file_get_contents($filePath);
            
            // If we have the Bencode utility, use it to modify the announce URL
            if (class_exists('\\Harment\\XBTTracker\\Util\\Bencode')) {
                $torrentData = \Harment\XBTTracker\Util\Bencode::decode($torrentContent);
                
                if (isset($torrentData['announce'])) {
                    $announceUrl = $this->options()->harmentXbtTrackerAnnounceUrl;
                    $separator = strpos($announceUrl, '?') !== false ? '&' : '?';
                    $torrentData['announce'] = $announceUrl . $separator . 'passkey=' . $passkey;
                    
                    return \Harment\XBTTracker\Util\Bencode::encode($torrentData);
                }
            }
            
            // Fallback: just return the original file if we can't modify it
            return $torrentContent;
        } catch (\Exception $e) {
            \XF::logException($e);
            return file_get_contents($filePath);
        }
    }
    
    /**
     * Check if user can download a torrent
     *
     * @param \Harment\XBTTracker\Entity\Torrent|array $torrent
     * @return bool
     */
    protected function canDownloadTorrent($torrent)
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->user_id) {
            return $this->options()->harmentXbtTrackerAllowGuestDownloads;
        }
        
        if (!$visitor->hasPermission('harmentXbtTracker', 'download')) {
            return false;
        }
        
        // If global freeleech is enabled, no ratio check needed
        if ($this->options()->harmentXbtTrackerGlobalFreeleech) {
            return true;
        }
        
        // If this torrent is freeleech, no ratio check needed
        if (is_array($torrent)) {
            $isFreeleech = !empty($torrent['is_freeleech']);
        } else {
            $isFreeleech = $torrent->is_freeleech;
        }
        
        if ($isFreeleech) {
            return true;
        }
        
        $userStats = $this->getUserStats();
        if (!$userStats) {
            return true; // No stats yet, allow download
        }
        
        // Check ratio requirement
        $requiredRatio = $this->options()->harmentXbtTrackerRequiredRatio;
        
        if ($requiredRatio > 0 && $userStats->ratio < $requiredRatio) {
            // Group exceptions
            $exemptGroups = $this->options()->harmentXbtTrackerRatioExemptGroups;
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
        return \XF::visitor()->hasPermission('harmentXbtTracker', 'upload');
    }
    
    /**
     * Check if user can edit a torrent
     *
     * @param \Harment\XBTTracker\Entity\Torrent $torrent
     * @return bool
     */
    protected function canEditTorrent($torrent)
    {
        $visitor = \XF::visitor();
        
        if ($visitor->hasPermission('harmentXbtTracker', 'moderateTorrents')) {
            return true;
        }
        
        if ($torrent->user_id == $visitor->user_id && $visitor->hasPermission('harmentXbtTracker', 'edit')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user can delete a torrent
     *
     * @param \Harment\XBTTracker\Entity\Torrent $torrent
     * @return bool
     */
    protected function canDeleteTorrent($torrent)
    {
        $visitor = \XF::visitor();
        
        if ($visitor->hasPermission('harmentXbtTracker', 'moderateTorrents')) {
            return true;
        }
        
        if ($torrent->user_id == $visitor->user_id && $visitor->hasPermission('harmentXbtTracker', 'delete')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user stats entity
     *
     * @return \Harment\XBTTracker\Entity\UserStats|null
     */
    protected function getUserStats()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->user_id) {
            return null;
        }
        
        $stats = $this->finder('Harment\XBTTracker:UserStats')
            ->where('user_id', $visitor->user_id)
            ->fetchOne();
            
        if (!$stats) {
            $stats = $this->em()->create('Harment\XBTTracker:UserStats');
            $stats->user_id = $visitor->user_id;
            $stats->save();
        }
        
        return $stats;
    }
    
    /**
     * Get torrent repository
     *
     * @return \Harment\XBTTracker\Repository\Torrent
     */
    protected function getTorrentRepo()
    {
        return $this->repository('Harment\XBTTracker:Torrent');
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