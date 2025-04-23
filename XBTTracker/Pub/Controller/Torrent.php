<?php

namespace Harment\XBTTracker\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use XF\Repository\Attachment;
use XF\Repository\User;
use XF\Service\User\TempChange;
use XF\Util\File;
use XF\Http\Upload;
use XF\Service\Validator\Error;

/**
 * Controller for torrent related actions
 */
class Torrent extends AbstractController
{
    /**
     * Display list of torrents with filtering and sorting options
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionList()
    {
        $this->assertCanView();
        
        $page = $this->filterPage();
        $perPage = 20;
        
        // Search parameters
        $categoryId = $this->filter('category_id', 'uint');
        $searchQuery = $this->filter('q', 'str');
        $sortOrder = $this->filter('sort', 'str', 'creation_date');
        $sortDirection = $this->filter('direction', 'str', 'desc');
        
        $validSorts = ['creation_date', 'title', 'size', 'seeders', 'leechers', 'completed', 'view_count'];
        if (!in_array($sortOrder, $validSorts)) {
            $sortOrder = 'creation_date';
        }
        
        $validDirections = ['asc', 'desc'];
        if (!in_array($sortDirection, $validDirections)) {
            $sortDirection = 'desc';
        }
        
        try {
            // Get torrent finder
            $torrentFinder = $this->getTorrentRepo()->getTorrentFinder();
            
            // Apply filters
            if ($categoryId) {
                $torrentFinder->where('category_id', $categoryId);
            }
            
            if ($searchQuery) {
                $torrentFinder->where('title', 'LIKE', '%' . $torrentFinder->escapeLike($searchQuery) . '%');
            }
            
            // Apply sort order
            $torrentFinder->order($sortOrder, $sortDirection);
            
            // Get total count for pagination
            $totalTorrents = $torrentFinder->total();
            
            // Apply pagination
            $torrentFinder->limitByPage($page, $perPage);
            
            // Get torrents
            $torrents = $torrentFinder->fetch();
            
            // Get categories for filtering
            $categories = $this->getCategoryRepo()->findCategoriesForList()->fetch();
        } catch (\Exception $e) {
            \XF::logException($e);
            $torrents = [];
            $totalTorrents = 0;
            $categories = [];
        }
        
        // Create pagination
        $viewParams = [
            'torrents' => $torrents,
            'categories' => $categories,
            'totalTorrents' => $totalTorrents,
            'page' => $page,
            'perPage' => $perPage,
            'categoryId' => $categoryId,
            'searchQuery' => $searchQuery,
            'sortOrder' => $sortOrder,
            'sortDirection' => $sortDirection
        ];
        
        return $this->view('Harment\XBTTracker:Torrent\List', 'xbt_torrent_list', $viewParams);
    }
    
    /**
     * View torrent details including files, TMDb info, and uploader information
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     */
    public function actionView(ParameterBag $params)
    {
        $this->assertCanView();
        
        // Process both parameter types for backward compatibility
        $torrentId = $params->get('torrent_id');
        $infoHash = $params->get('info_hash');
        
        if (!$torrentId && !$infoHash) {
            $torrentId = $this->filter('torrent_id', 'uint');
            $infoHash = $this->filter('info_hash', 'str');
        }
        
        if (!$torrentId && !$infoHash) {
            return $this->error(\XF::phrase('harment_xbttracker_no_torrent_identifier_provided'));
        }
        
        try {
            // Find torrent by ID or info_hash
            $torrent = null;
            if ($torrentId) {
                $torrent = $this->em()->find('Harment\XBTTracker:Torrent', $torrentId);
            } else if ($infoHash) {
                $torrent = $this->getTorrentRepo()->findTorrentByInfoHash($infoHash)->fetchOne();
            }
            
            if (!$torrent) {
                return $this->error(\XF::phrase('harment_xbttracker_torrent_not_found'));
            }
            
            // Get torrent files
            $files = $this->getTorrentRepo()->getTorrentFiles($torrent->info_hash);
            
            // Get TMDb info if available
            $tmdbInfo = null;
            if ($torrent->tmdb_id) {
                $tmdbInfo = $this->app()->finder('Harment\XBTTracker:TmdbData')
                    ->where('tmdb_id', $torrent->tmdb_id)
                    ->fetchOne();
            }
            
            // Update view count
            $torrent->fastUpdate('view_count', $torrent->view_count + 1);
            
            // Get uploader info
            $uploader = $this->em()->find('XF:User', $torrent->user_id);
            
            // Check if user can download
            $visitor = \XF::visitor();
            $canDownload = $visitor->hasPermission('xbtTracker', 'download');
            
            // Check ratio requirements
            $hasRatioRequirement = false;
            $ratioRequirement = $this->options()->xbtTrackerRequiredRatio;
            
            if ($ratioRequirement > 0 && $canDownload) {
                // Check if user is in exempt group
                $exemptGroups = $this->options()->xbtTrackerRatioExemptGroups ?: [];
                $userGroups = $visitor->secondary_group_ids ? explode(',', $visitor->secondary_group_ids) : [];
                $userGroups[] = $visitor->user_group_id;
                
                $isExempt = false;
                foreach ($userGroups as $groupId) {
                    if (in_array($groupId, $exemptGroups)) {
                        $isExempt = true;
                        break;
                    }
                }
                
                if (!$isExempt) {
                    $userStats = $this->getUserStatsRepo()->getUserStats($visitor->user_id);
                    if ($userStats) {
                        $ratio = $userStats->getRatio();
                        if ($ratio < $ratioRequirement) {
                            $canDownload = false;
                            $hasRatioRequirement = true;
                        }
                    }
                }
            }
            
            // Check if freeleech is active
            $isFreeleech = $torrent->is_freeleech || $this->options()->xbtTrackerGlobalFreeleech;
            
            // Check if thank you is required
            $forceThankYou = $this->options()->xbtTrackerForceThankYou;
            $hasThanked = false;
            
            if ($forceThankYou && $visitor->user_id) {
                $hasThanked = $this->getTorrentRepo()->hasThankedTorrent($torrent->torrent_id, $visitor->user_id);
            }
            
            $viewParams = [
                'torrent' => $torrent,
                'uploader' => $uploader,
                'tmdbInfo' => $tmdbInfo,
                'files' => $files,
                'canDownload' => $canDownload,
                'hasRatioRequirement' => $hasRatioRequirement,
                'ratioRequirement' => $ratioRequirement,
                'isFreeleech' => $isFreeleech,
                'forceThankYou' => $forceThankYou,
                'hasThanked' => $hasThanked
            ];
            
            return $this->view('Harment\XBTTracker:Torrent\View', 'xbt_torrent_view', $viewParams);
        } catch (\Exception $e) {
            \XF::logException($e);
            return $this->error(\XF::phrase('harment_xbttracker_error_loading_torrent_data'));
        }
    }
    
    /**
     * Download torrent file with user's passkey
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View|\XF\Http\Response
     */
    public function actionDownload(ParameterBag $params)
    {
        $this->assertCanView();
        $this->assertCanDownload();
        
        // Process both parameter types for backward compatibility
        $torrentId = $params->get('torrent_id');
        $infoHash = $params->get('info_hash');
        
        if (!$torrentId && !$infoHash) {
            $torrentId = $this->filter('torrent_id', 'uint');
            $infoHash = $this->filter('info_hash', 'str');
        }
        
        if (!$torrentId && !$infoHash) {
            return $this->error(\XF::phrase('harment_xbttracker_no_torrent_identifier_provided'));
        }
        
        try {
            // Find torrent by ID or info_hash
            $torrent = null;
            if ($torrentId) {
                $torrent = $this->em()->find('Harment\XBTTracker:Torrent', $torrentId);
            } else if ($infoHash) {
                $torrent = $this->getTorrentRepo()->findTorrentByInfoHash($infoHash)->fetchOne();
            }
            
            if (!$torrent) {
                return $this->error(\XF::phrase('harment_xbttracker_torrent_not_found'));
            }
            
            // Check ratio requirements
            $visitor = \XF::visitor();
            $ratioRequirement = $this->options()->xbtTrackerRequiredRatio;
            
            if ($ratioRequirement > 0) {
                // Check if user is in exempt group
                $exemptGroups = $this->options()->xbtTrackerRatioExemptGroups ?: [];
                $userGroups = $visitor->secondary_group_ids ? explode(',', $visitor->secondary_group_ids) : [];
                $userGroups[] = $visitor->user_group_id;
                
                $isExempt = false;
                foreach ($userGroups as $groupId) {
                    if (in_array($groupId, $exemptGroups)) {
                        $isExempt = true;
                        break;
                    }
                }
                
                if (!$isExempt) {
                    $userStats = $this->getUserStatsRepo()->getUserStats($visitor->user_id);
                    if ($userStats) {
                        $ratio = $userStats->getRatio();
                        if ($ratio < $ratioRequirement) {
                            return $this->error(\XF::phrase('xbt_ratio_too_low'));
                        }
                    }
                }
            }
            
            // Check if thank you is required
            $forceThankYou = $this->options()->xbtTrackerForceThankYou;
            
            if ($forceThankYou && $visitor->user_id) {
                $hasThanked = $this->getTorrentRepo()->hasThankedTorrent($torrent->torrent_id, $visitor->user_id);
                if (!$hasThanked) {
                    return $this->error(\XF::phrase('xbt_thank_to_download'));
                }
            }
            
            // Get the user's passkey or create one
            $userStats = $this->getUserStatsRepo()->getOrCreateUserStats($visitor->user_id);
            
            // Check if we should use Service or direct handling
            if (class_exists('Harment\XBTTracker\Service\Torrent\Download')) {
                // Use service if available
                /** @var \Harment\XBTTracker\Service\Torrent\Download $torrentService */
                $torrentService = $this->service('Harment\XBTTracker:Torrent\Download', $torrent, $userStats);
                $torrentFile = $torrentService->getTorrentFile();
                
                if (!$torrentFile) {
                    return $this->error(\XF::phrase('xbt_requested_torrent_not_found'));
                }
            } else {
                // Direct handling if service is not available
                $filePath = \XF::getRootDirectory() . '/' . $torrent->file_path;
                if (!file_exists($filePath)) {
                    return $this->error(\XF::phrase('xbt_requested_torrent_not_found'));
                }
                
                // Modify the torrent file with user's passkey
                $torrentData = file_get_contents($filePath);
                
                // Check if BDecode function exists
                if (!function_exists('BDecode')) {
                    require_once(\XF::getRootDirectory() . '/src/addons/Harment/XBTTracker/Functions/Bencoding.php');
                }
                
                $decodedTorrent = \BDecode($torrentData);
                if (!$decodedTorrent) {
                    return $this->error(\XF::phrase('xbt_invalid_torrent_file_format'));
                }
                
                // Add announce URL with passkey
                $announceUrl = $this->options()->xbtTrackerAnnounceURL;
                if (strpos($announceUrl, '?') === false) {
                    $announceUrl .= '?passkey=' . $userStats->passkey;
                } else {
                    $announceUrl .= '&passkey=' . $userStats->passkey;
                }
                
                $decodedTorrent['announce'] = $announceUrl;
                
                // Remove any existing announce-list to avoid conflicts
                if (isset($decodedTorrent['announce-list'])) {
                    unset($decodedTorrent['announce-list']);
                }
                
                $torrentFile = \BEncode($decodedTorrent);
            }
            
            // Update download statistics
            if (method_exists($torrent, 'incrementCompletedCount')) {
                $torrent->incrementCompletedCount();
            } else {
                // Manual increment if method doesn't exist
                $torrent->fastUpdate('completed', $torrent->completed + 1);
            }
            
            // Set file download headers
            $this->setResponseType('raw');
            
            $fileName = $torrent->title;
            $fileName = preg_replace('/[^a-z0-9_\-\.]/i', '_', $fileName);
            $fileName = $fileName . '.torrent';
            
            $response = $this->response();
            $response->header('Content-Type', 'application/x-bittorrent');
            $response->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
            $response->body($torrentFile);
            
            return $response;
        } catch (\Exception $e) {
            \XF::logException($e);
            return $this->error(\XF::phrase('xbt_error_generating_torrent_file'));
        }
    }
    
    /**
     * Upload torrent form
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionUpload()
    {
        $this->assertCanView();
        $this->assertCanUpload();
        
        try {
            $categories = $this->getCategoryRepo()->findCategoriesForList()->fetch();
            
            $viewParams = [
                'categories' => $categories,
                'announceUrl' => $this->options()->xbtTrackerAnnounceURL
            ];
            
            return $this->view('Harment\XBTTracker:Torrent\Upload', 'xbt_torrent_upload', $viewParams);
        } catch (\Exception $e) {
            \XF::logException($e);
            return $this->error(\XF::phrase('xbt_error_loading_category_data'));
        }
    }
    
    /**
     * Handle torrent upload
     *
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect
     */
    public function actionUploadSave()
    {
        $this->assertCanView();
        $this->assertCanUpload();
        $this->assertPostOnly();
        
        // Get form data
        $input = $this->filter([
            'title' => 'str',
            'description' => 'str',
            'category_id' => 'uint',
            'video_quality' => 'str',
            'audio_format' => 'str',
            'audio_channels' => 'str',
            'tmdb_id' => 'uint',
            'is_freeleech' => 'bool'
        ]);
        
        // Validate form data
        $errors = [];
        
        if (!$input['title']) {
            $errors[] = \XF::phrase('xbt_torrent_title_required');
        }
        
        if (!$input['category_id']) {
            $errors[] = \XF::phrase('xbt_torrent_category_required');
        }
        
        // Upload torrent file
        $upload = $this->request->getFile('torrent_file', false);
        if (!$upload) {
            $errors[] = \XF::phrase('xbt_torrent_file_required');
        } else {
            // Validate file extension
            $fileName = $upload->getFileName();
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($ext != 'torrent') {
                $errors[] = \XF::phrase('xbt_invalid_torrent_file_extension');
            }
        }
        
        if ($errors) {
            return $this->error($errors);
        }
        
        try {
            // Try to use service if available
            if (class_exists('Harment\XBTTracker\Service\Torrent\Creator')) {
                /** @var \Harment\XBTTracker\Service\Torrent\Creator $torrentService */
                $torrentService = $this->service('Harment\XBTTracker:Torrent\Creator');
                $torrentService->setTorrentFile($upload);
                
                // Upload poster file
                $posterUpload = $this->request->getFile('poster_file', false);
                if ($posterUpload) {
                    // Validate poster file
                    $posterFileName = $posterUpload->getFileName();
                    $posterExt = strtolower(pathinfo($posterFileName, PATHINFO_EXTENSION));
                    $validPosterExts = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($posterExt, $validPosterExts)) {
                        return $this->error(\XF::phrase('xbt_invalid_poster_file_extension'));
                    }
                    
                    $torrentService->setPosterFile($posterUpload);
                }
                
                $visitor = \XF::visitor();
                
                // Set torrent data
                $torrentService->setData([
                    'title' => $input['title'],
                    'description' => $input['description'],
                    'category_id' => $input['category_id'],
                    'user_id' => $visitor->user_id,
                    'video_quality' => $input['video_quality'],
                    'audio_format' => $input['audio_format'],
                    'audio_channels' => $input['audio_channels'],
                    'tmdb_id' => $input['tmdb_id'] ?: 0,
                    'is_freeleech' => $input['is_freeleech'],
                    'creation_date' => \XF::$time
                ]);
                
                // Validate and save the torrent
                if (!$torrentService->validate($serviceErrors)) {
                    return $this->error($serviceErrors);
                }
                
                $torrent = $torrentService->save();
                
                // Redirect to torrent page
                return $this->redirect($this->buildLink('torrents/view', $torrent));
            } else {
                // Manual handling if service is not available
                $visitor = \XF::visitor();
                
                // Create torrent entity
                $torrent = $this->em()->create('Harment\XBTTracker:Torrent');
                $torrent->title = $input['title'];
                $torrent->description = $input['description'];
                $torrent->category_id = $input['category_id'];
                $torrent->user_id = $visitor->user_id;
                $torrent->video_quality = $input['video_quality'] ?? '';
                $torrent->audio_format = $input['audio_format'] ?? '';
                $torrent->audio_channels = $input['audio_channels'] ?? '';
                $torrent->tmdb_id = $input['tmdb_id'] ?? 0;
                $torrent->is_freeleech = $input['is_freeleech'] ?? false;
                $torrent->creation_date = \XF::$time;
                
                // Process torrent file
                $tempFile = $upload->getTempFile();
                $torrentData = $this->parseTorrentFile($tempFile);
                
                if (!$torrentData || empty($torrentData['info_hash'])) {
                    return $this->error(\XF::phrase('xbt_invalid_torrent_file_format'));
                }
                
                $torrent->info_hash = $torrentData['info_hash'];
                $torrent->size = $torrentData['size'] ?? 0;
                
                // Save torrent file
                $torrentPath = $this->options()->xbtTrackerTorrentPath ?? 'data/torrents';
                $fileName = $torrent->info_hash . '.torrent';
                $fileDir = \XF::getRootDirectory() . '/' . $torrentPath;
                
                if (!file_exists($fileDir)) {
                    mkdir($fileDir, 0755, true);
                }
                
                $filePath = $fileDir . '/' . $fileName;
                if (!rename($tempFile, $filePath)) {
                    return $this->error(\XF::phrase('xbt_error_saving_torrent_file'));
                }
                
                $torrent->file_path = $torrentPath . '/' . $fileName;
                
                // Process poster file
                $posterUpload = $this->request->getFile('poster_file', false);
                if ($posterUpload && $posterUpload->isValid()) {
                    $posterFileName = $posterUpload->getFileName();
                    $posterExt = strtolower(pathinfo($posterFileName, PATHINFO_EXTENSION));
                    $validPosterExts = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($posterExt, $validPosterExts)) {
                        return $this->error(\XF::phrase('xbt_invalid_poster_file_extension'));
                    }
                    
                    $posterTempFile = $posterUpload->getTempFile();
                    $posterFileName = $torrent->info_hash . '.' . $posterExt;
                    $posterDir = $fileDir . '/posters';
                    
                    if (!file_exists($posterDir)) {
                        mkdir($posterDir, 0755, true);
                    }
                    
                    $posterPath = $posterDir . '/' . $posterFileName;
                    if (!rename($posterTempFile, $posterPath)) {
                        return $this->error(\XF::phrase('xbt_poster_file_invalid'));
                    }
                    
                    $torrent->poster_path = $torrentPath . '/posters/' . $posterFileName;
                }
                
                // Save the torrent entity
                $torrent->save();
                
                // Redirect to torrent view page
                if (isset($torrent->torrent_id)) {
                    return $this->redirect($this->buildLink('torrents/view', $torrent));
                } else {
                    return $this->redirect($this->buildLink('torrents/view', ['info_hash' => $torrent->info_hash]));
                }
            }
        } catch (\Exception $e) {
            \XF::logException($e);
            return $this->error(\XF::phrase('xbt_error_uploading_torrent') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Edit torrent form
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     */
    public function actionEdit(ParameterBag $params)
    {
        $this->assertCanView();
        
        // Process both parameter types for backward compatibility
        $torrentId = $params->get('torrent_id');
        $infoHash = $params->get('info_hash');
        
        if (!$torrentId && !$infoHash) {
            $torrentId = $this->filter('torrent_id', 'uint');
            $infoHash = $this->filter('info_hash', 'str');
        }
        
        if (!$torrentId && !$infoHash) {
            return $this->error(\XF::phrase('harment_xbttracker_no_torrent_identifier_provided'));
        }
        
        try {
            // Find torrent by ID or info_hash
            $torrent = null;
            if ($torrentId) {
                $torrent = $this->em()->find('Harment\XBTTracker:Torrent', $torrentId);
            } else if ($infoHash) {
                $torrent = $this->getTorrentRepo()->findTorrentByInfoHash($infoHash)->fetchOne();
            }
            
            if (!$torrent) {
                return $this->error(\XF::phrase('harment_xbttracker_torrent_not_found'));
            }
            
            // Check if user can edit this torrent
            $this->assertCanEditTorrent($torrent);
            
            // Get categories for dropdown
            $categories = $this->getCategoryRepo()->findCategoriesForList()->fetch();
            
            $viewParams = [
                'torrent' => $torrent,
                'categories' => $categories
            ];
            
            return $this->view('Harment\XBTTracker:Torrent\Edit', 'xbt_torrent_edit', $viewParams);
        } catch (\Exception $e) {
            \XF::logException($e);
            return $this->error(\XF::phrase('xbt_error_loading_torrent_data'));
        }
    }
    
    /**
     * Handle torrent edit
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect
     */
    public function actionEditSave(ParameterBag $params)
    {
        $this->assertCanView();
        $this->assertPostOnly();
        
        // Process both parameter types for backward compatibility
        $torrentId = $params->get('torrent_id');
        $infoHash = $params->get('info_hash');
        
        if (!$torrentId && !$infoHash) {
            $torrentId = $this->filter('torrent_id', 'uint');
            $infoHash = $this->filter('info_hash', 'str');
        }
        
        if (!$torrentId && !$infoHash) {
            return $this->error(\XF::phrase('harment_xbttracker_no_torrent_identifier_provided'));
        }
        
        try {
            // Find torrent by ID or info_hash
            $torrent = null;
            if ($torrentId) {
                $torrent = $this->em()->find('Harment\XBTTracker:Torrent', $torrentId);
            } else if ($infoHash) {
                $torrent = $this->getTorrentRepo()->findTorrentByInfoHash($infoHash)->fetchOne();
            }
            
            if (!$torrent) {
                return $this->error(\XF::phrase('harment_xbttracker_torrent_not_found'));
            }
            
            // Check if user can edit this torrent
            $this->assertCanEditTorrent($torrent);
            
            // Get form data
            $input = $this->filter([
                'title' => 'str',
                'description' => 'str',
                'category_id' => 'uint',
                'video_quality' => 'str',
                'audio_format' => 'str',
                'audio_channels' => 'str',
                'tmdb_id' => 'uint',
                'is_freeleech' => 'bool'
            ]);
            
            // Validate form data
            $errors = [];
            
            if (!$input['title']) {
                $errors[] = \XF::phrase('xbt_torrent_title_required');
            }
            
            if (!$input['category_id']) {
                $errors[] = \XF::phrase('xbt_torrent_category_required');
            }
            
            if ($errors) {
                return $this->error($errors);
            }
            
            // Try to use service if available
            if (class_exists('Harment\XBTTracker\Service\Torrent\Editor')) {
                /** @var \Harment\XBTTracker\Service\Torrent\Editor $torrentService */
                $torrentService = $this->service('Harment\XBTTracker:Torrent\Editor', $torrent);
                
                // Upload poster file
                $posterUpload = $this->request->getFile('poster_file', false);
                if ($posterUpload) {
                    // Validate poster file
                    $posterFileName = $posterUpload->getFileName();
                    $posterExt = strtolower(pathinfo($posterFileName, PATHINFO_EXTENSION));
                    $validPosterExts = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($posterExt, $validPosterExts)) {
                        return $this->error(\XF::phrase('xbt_invalid_poster_file_extension'));
                    }
                    
                    $torrentService->setPosterFile($posterUpload);
                }
                
                // Set torrent data
                $torrentService->setData([
                    'title' => $input['title'],
                    'description' => $input['description'],
                    'category_id' => $input['category_id'],
                    'video_quality' => $input['video_quality'],
                    'audio_format' => $input['audio_format'],
                    'audio_channels' => $input['audio_channels'],
                    'tmdb_id' => $input['tmdb_id'] ?: 0,
                    'is_freeleech' => $input['is_freeleech']
                ]);
                
                // Validate and save the torrent
                if (!$torrentService->validate($serviceErrors)) {
                    return $this->error($serviceErrors);
                }
                
                $torrent = $torrentService->save();
                
                // Redirect to torrent page
                return $this->redirect($this->buildLink('torrents/view', $torrent));
            } else {
                // Manual handling if service is not available
                
                // Update torrent fields
                $torrent->title = $input['title'];
                $torrent->description = $input['description'];
                $torrent->category_id = $input['category_id'];
                $torrent->video_quality = $input['video_quality'] ?? '';
                $torrent->audio_format = $input['audio_format'] ?? '';
                $torrent->audio_channels = $input['audio_channels'] ?? '';
                $torrent->tmdb_id = $input['tmdb_id'] ?? 0;
                $torrent->is_freeleech = $input['is_freeleech'] ?? false;
                
                // Process poster file
                $posterUpload = $this->request->getFile('poster_file', false);
                if ($posterUpload && $posterUpload->isValid()) {
                    $posterFileName = $posterUpload->getFileName();
                    $posterExt = strtolower(pathinfo($posterFileName, PATHINFO_EXTENSION));
                    $validPosterExts = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($posterExt, $validPosterExts)) {
                        return $this->error(\XF::phrase('xbt_invalid_poster_file_extension'));
                    }
                    
                    $posterTempFile = $posterUpload->getTempFile();
                    $posterFileName = $torrent->info_hash . '.' . $posterExt;
                    $torrentPath = $this->options()->xbtTrackerTorrentPath ?? 'data/torrents';
                    $posterDir = \XF::getRootDirectory() . '/' . $torrentPath . '/posters';
                    
                    if (!file_exists($posterDir)) {
                        mkdir($posterDir, 0755, true);
                    }
                    
                    $posterPath = $posterDir . '/' . $posterFileName;
                    
                    // Delete old poster file if exists
                    if (!empty($torrent->poster_path) && file_exists(\XF::getRootDirectory() . '/' . $torrent->poster_path)) {
                        @unlink(\XF::getRootDirectory() . '/' . $torrent->poster_path);
                    }
                    
                    if (!rename($posterTempFile, $posterPath)) {
                        return $this->error(\XF::phrase('xbt_poster_file_invalid'));
                    }
                    
                    $torrent->poster_path = $torrentPath . '/posters/' . $posterFileName;
                }
                
                // Save the torrent entity
                $torrent->save();
                
                // Redirect to torrent view page
                if (isset($torrent->torrent_id)) {
                    return $this->redirect($this->buildLink('torrents/view', $torrent));
                } else {
                    return $this->redirect($this->buildLink('torrents/view', ['info_hash' => $torrent->info_hash]));
                }
            }
        } catch (\Exception $e) {
            \XF::logException($e);
            return $this->error(\XF::phrase('xbt_error_updating_torrent') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Delete torrent confirmation
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     */
    public function actionDelete(ParameterBag $params)
    {
        $this->assertCanView();
        
        // Process both parameter types for backward compatibility
        $torrentId = $params->get('torrent_id');
        $infoHash = $params->get('info_hash');
        
        if (!$torrentId && !$infoHash) {
            $torrentId = $this->filter('torrent_id', 'uint');
            $infoHash = $this->filter('info_hash', 'str');
        }
        
        if (!$torrentId && !$infoHash) {
            return $this->error(\XF::phrase('harment_xbttracker_no_torrent_identifier_provided'));
        }
        
        try {
            // Find torrent by ID or info_hash
            $torrent = null;
            if ($torrentId) {
                $torrent = $this->em()->find('Harment\XBTTracker:Torrent', $torrentId);
            } else if ($infoHash) {
                $torrent = $this->getTorrentRepo()->findTorrentByInfoHash($infoHash)->fetchOne();
            }
            
            if (!$torrent) {
                return $this->error(\XF::phrase('harment_xbttracker_torrent_not_found'));
            }
            
            // Check if user can delete this torrent
            $this->assertCanDeleteTorrent($torrent);
            
            $viewParams = [
                'torrent' => $torrent
            ];
            
            return $this->view('Harment\XBTTracker:Torrent\Delete', 'xbt_torrent_delete', $viewParams);
        } catch (\Exception $e) {
            \XF::logException($e);
            return $this->error(\XF::phrase('xbt_error_loading_torrent_data'));
        }
    }
    
    /**
     * Handle torrent deletion
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect
     */
    public function actionDeleteConfirm(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertCanView();
        
        // Process both parameter types for backward compatibility
        $torrentId = $params->get('torrent_id');
        $infoHash = $params->get('info_hash');
        
        if (!$torrentId && !$infoHash) {
            $torrentId = $this->filter('torrent_id', 'uint');
            $infoHash = $this->filter('info_hash', 'str');
        }
        
        if (!$torrentId && !$infoHash) {
            return $this->error(\XF::phrase('harment_xbttracker_no_torrent_identifier_provided'));
        }
        
        try {
            // Find torrent by ID or info_hash
            $torrent = null;
            if ($torrentId) {
                $torrent = $this->em()->find('Harment\XBTTracker:Torrent', $torrentId);
            } else if ($infoHash) {
                $torrent = $this->getTorrentRepo()->findTorrentByInfoHash($infoHash)->fetchOne();
            }
            
            if (!$torrent) {
                return $this->error(\XF::phrase('harment_xbttracker_torrent_not_found'));
            }
            
            // Check if user can delete this torrent
            $this->assertCanDeleteTorrent($torrent);
            
            // Try to use service if available
            if (class_exists('Harment\XBTTracker\Service\Torrent\Deleter')) {
                /** @var \Harment\XBTTracker\Service\Torrent\Deleter $torrentService */
                $torrentService = $this->service('Harment\XBTTracker:Torrent\Deleter', $torrent);
                $torrentService->delete();
            } else {
                // Manual deletion if service is not available
                
                // Delete torrent file
                if (!empty($torrent->file_path)) {
                    $filePath = \XF::getRootDirectory() . '/' . $torrent->file_path;
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
                
                // Delete poster file
                if (!empty($torrent->poster_path)) {
                    $posterPath = \XF::getRootDirectory() . '/' . $torrent->poster_path;
                    if (file_exists($posterPath)) {
                        @unlink($posterPath);
                    }
                }
                
                // Delete torrent record
                $torrent->delete();
            }
            
            return $this->redirect($this->buildLink('torrents'));
        } catch (\Exception $e) {
            \XF::logException($e);
            return $this->error(\XF::phrase('xbt_error_deleting_torrent') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Thank torrent uploader
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect
     */
    public function actionThanks(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertCanView();
        $this->assertCanDownload();
        
        // Process both parameter types for backward compatibility
        $torrentId = $params->get('torrent_id');
        $infoHash = $params->get('info_hash');
        
        if (!$torrentId && !$infoHash) {
            $torrentId = $this->filter('torrent_id', 'uint');
            $infoHash = $this->filter('info_hash', 'str');
        }
        
        if (!$torrentId && !$infoHash) {
            return $this->error(\XF::phrase('harment_xbttracker_no_torrent_identifier_provided'));
        }
        
        try {
            // Find torrent by ID or info_hash
            $torrent = null;
            if ($torrentId) {
                $torrent = $this->em()->find('Harment\XBTTracker:Torrent', $torrentId);
            } else if ($infoHash) {
                $torrent = $this->getTorrentRepo()->findTorrentByInfoHash($infoHash)->fetchOne();
            }
            
            if (!$torrent) {
                return $this->error(\XF::phrase('harment_xbttracker_torrent_not_found'));
            }
            
            $visitor = \XF::visitor();
            
            // Check if already thanked
            if ($this->getTorrentRepo()->hasThankedTorrent($torrent->torrent_id, $visitor->user_id)) {
                return $this->redirect($this->buildLink('torrents/view', $torrent));
            }
            
            // Record thank you
            $this->getTorrentRepo()->thankTorrent($torrent->torrent_id, $visitor->user_id);
            
            // Success message
            return $this->redirect(
                $this->buildLink('torrents/view', $torrent),
                \XF::phrase('xbt_torrent_thank_recorded')
            );
        } catch (\Exception $e) {
            \XF::logException($e);
            return $this->error(\XF::phrase('xbt_error_recording_thank_you'));
        }
    }
    
    /**
     * Parse torrent file to extract metadata
     *
     * @param string $filePath
     * @return array|null
     */
    protected function parseTorrentFile($filePath)
    {
        if (!file_exists($filePath)) {
            return null;
        }
        
        try {
            $torrentData = file_get_contents($filePath);
            
            // Check if BDecode function exists
            if (!function_exists('BDecode')) {
                require_once(\XF::getRootDirectory() . '/src/addons/Harment/XBTTracker/Functions/Bencoding.php');
            }
            
            $decodedTorrent = \BDecode($torrentData);
            
            if (!$decodedTorrent || empty($decodedTorrent['info'])) {
                return null;
            }
            
            $result = [];
            
            // Extract info_hash
            $encodedInfo = \BEncode($decodedTorrent['info']);
            $result['info_hash'] = bin2hex(sha1($encodedInfo, true));
            
            // Calculate size
            $size = 0;
            if (isset($decodedTorrent['info']['files']) && is_array($decodedTorrent['info']['files'])) {
                // Multi-file torrent
                foreach ($decodedTorrent['info']['files'] as $file) {
                    if (isset($file['length'])) {
                        $size += $file['length'];
                    }
                }
            } elseif (isset($decodedTorrent['info']['length'])) {
                // Single file torrent
                $size = $decodedTorrent['info']['length'];
            }
            
            $result['size'] = $size;
            
            // Extract name
            if (isset($decodedTorrent['info']['name'])) {
                $result['name'] = $decodedTorrent['info']['name'];
            }
            
            // Extract announce URL
            if (isset($decodedTorrent['announce'])) {
                $result['announce'] = $decodedTorrent['announce'];
            }
            
            // Extract file list for multi-file torrents
            if (isset($decodedTorrent['info']['files']) && is_array($decodedTorrent['info']['files'])) {
                $result['files'] = [];
                
                foreach ($decodedTorrent['info']['files'] as $file) {
                    if (isset($file['path']) && is_array($file['path']) && isset($file['length'])) {
                        $result['files'][] = [
                            'path' => implode('/', $file['path']),
                            'size' => $file['length']
                        ];
                    }
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            \XF::logException($e);
            return null;
        }
    }
    
    /**
     * Assert that the current user can view torrents
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCanView()
    {
        $visitor = \XF::visitor();
        if (!$visitor->hasPermission('xbtTracker', 'view')) {
            throw $this->exception($this->noPermission());
        }
    }
    
    /**
     * Assert that the current user can download torrents
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCanDownload()
    {
        $visitor = \XF::visitor();
        if (!$visitor->hasPermission('xbtTracker', 'download')) {
            throw $this->exception($this->noPermission());
        }
        
        // Make sure the user has a passkey
        if (empty($visitor->xbt_passkey)) {
            // If using UserStats entity, create passkey there
            $userStats = $this->getUserStatsRepo()->getOrCreateUserStats($visitor->user_id);
            
            // Also set it on the user entity for compatibility
            $visitor->xbt_passkey = $userStats->passkey;
            $visitor->save();
        }
    }
    
    /**
     * Assert that the current user can upload torrents
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCanUpload()
    {
        $visitor = \XF::visitor();
        if (!$visitor->hasPermission('xbtTracker', 'upload')) {
            throw $this->exception($this->noPermission());
        }
    }
    
    /**
     * Assert that the requested torrent exists and is editable by the current user
     *
     * @param \Harment\XBTTracker\Entity\Torrent $torrent
     * @return void
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCanEditTorrent($torrent)
    {
        $visitor = \XF::visitor();
        
        // Allow edit if moderator
        if ($visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
            return;
        }
        
        // Allow edit if owner and has edit permission
        if ($visitor->user_id == $torrent->user_id && $visitor->hasPermission('xbtTracker', 'edit')) {
            return;
        }
        
        throw $this->exception($this->noPermission());
    }
    
    /**
     * Assert that the requested torrent exists and is deletable by the current user
     *
     * @param \Harment\XBTTracker\Entity\Torrent $torrent
     * @return void
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCanDeleteTorrent($torrent)
    {
        $visitor = \XF::visitor();
        
        // Allow delete if moderator
        if ($visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
            return;
        }
        
        // Allow delete if owner and has delete permission
        if ($visitor->user_id == $torrent->user_id && $visitor->hasPermission('xbtTracker', 'delete')) {
            return;
        }
        
        throw $this->exception($this->noPermission());
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
     * Get category repository
     *
     * @return \Harment\XBTTracker\Repository\Category
     */
    protected function getCategoryRepo()
    {
        return $this->repository('Harment\XBTTracker:Category');
    }
    
    /**
     * Get user stats repository
     *
     * @return \Harment\XBTTracker\Repository\UserStats
     */
    protected function getUserStatsRepo()
    {
        return $this->repository('Harment\XBTTracker:UserStats');
    }
}