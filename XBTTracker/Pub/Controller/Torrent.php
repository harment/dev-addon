<?php

namespace XBTTracker\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use XF\Repository\Attachment;
use XF\Repository\User;
use XF\Service\User\TempChange;
use XF\Util\File;
use XF\Http\Upload;
use XF\Service\Validator\Error;

class Torrent extends AbstractController
{
    /**
     * Display list of torrents
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
        
        return $this->view('XBTTracker:Torrent\List', 'xbt_torrent_list', $viewParams);
    }
    
    /**
     * View torrent details
     */
    public function actionView(ParameterBag $params)
    {
        $this->assertCanView();
        
        $torrentId = $params->get('torrent_id');
        $torrent = $this->assertViewableTorrent($torrentId);
        
        // Get torrent files
        $files = $this->getTorrentRepo()->getTorrentFiles($torrent->info_hash);
        
        // Get TMDb info if available
        $tmdbInfo = null;
        if ($torrent->tmdb_id) {
            $tmdbInfo = $this->app()->finder('XBTTracker:TmdbData')
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
        
        return $this->view('XBTTracker:Torrent\View', 'xbt_torrent_view', $viewParams);
    }
    
    /**
     * Download torrent file
     */
    public function actionDownload(ParameterBag $params)
    {
        $this->assertCanView();
        $this->assertCanDownload();
        
        $torrentId = $params->get('torrent_id');
        $torrent = $this->assertViewableTorrent($torrentId);
        
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
        
        // Get the torrent file and modify it with user's passkey
        $torrentService = $this->service('XBTTracker:Torrent\Download', $torrent, $userStats);
        
        // Create the torrent file for download
        $torrentFile = $torrentService->getTorrentFile();
        
        if (!$torrentFile) {
            return $this->error(\XF::phrase('xbt_requested_torrent_not_found'));
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
    }
    
    /**
     * Upload torrent form
     */
    public function actionUpload()
    {
        $this->assertCanView();
        $this->assertCanUpload();
        
        $categories = $this->getCategoryRepo()->findCategoriesForList()->fetch();
        
        $viewParams = [
            'categories' => $categories
        ];
        
        return $this->view('XBTTracker:Torrent\Upload', 'xbt_torrent_upload', $viewParams);
    }
    
    /**
     * Handle torrent upload
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
        
        // Upload poster file
        $posterPath = '';
        $posterUpload = $this->request->getFile('poster_file', false);
        if ($posterUpload) {
            // Validate file extension
            $posterFileName = $posterUpload->getFileName();
            $posterExt = strtolower(pathinfo($posterFileName, PATHINFO_EXTENSION));
            $validPosterExts = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($posterExt, $validPosterExts)) {
                $errors[] = \XF::phrase('xbt_invalid_poster_file_extension');
            }
        }
        
        if ($errors) {
            return $this->error($errors);
        }
        
        // Create the torrent
        $torrentService = $this->service('XBTTracker:Torrent\Creator');
        $torrentService->setTorrentFile($upload);
        
        if ($posterUpload) {
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
        if (!$torrentService->validate($errors)) {
            return $this->error($errors);
        }
        
        $torrent = $torrentService->save();
        
        // Redirect to torrent page
        return $this->redirect($this->buildLink('torrents/view', $torrent));
    }
    
    /**
     * Edit torrent form
     */
    public function actionEdit(ParameterBag $params)
    {
        $this->assertCanView();
        
        $torrentId = $params->get('torrent_id');
        $torrent = $this->assertEditableTorrent($torrentId);
        
        $categories = $this->getCategoryRepo()->findCategoriesForList()->fetch();
        
        $viewParams = [
            'torrent' => $torrent,
            'categories' => $categories
        ];
        
        return $this->view('XBTTracker:Torrent\Edit', 'xbt_torrent_edit', $viewParams);
    }
    
    /**
     * Handle torrent edit
     */
    public function actionEditSave(ParameterBag $params)
    {
        $this->assertCanView();
        $this->assertPostOnly();
        
        $torrentId = $params->get('torrent_id');
        $torrent = $this->assertEditableTorrent($torrentId);
        
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
        
        // Create the editor service
        $torrentService = $this->service('XBTTracker:Torrent\Editor', $torrent);
        
        // Upload poster file
        $posterUpload = $this->request->getFile('poster_file', false);
        if ($posterUpload) {
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
        if (!$torrentService->validate($errors)) {
            return $this->error($errors);
        }
        
        $torrent = $torrentService->save();
        
        // Redirect to torrent page
        return $this->redirect($this->buildLink('torrents/view', $torrent));
    }
    
    /**
     * Delete torrent confirmation
     */
    public function actionDelete(ParameterBag $params)
    {
        $this->assertCanView();
        
        $torrentId = $params->get('torrent_id');
        $torrent = $this->assertDeletableTorrent($torrentId);
        
        $viewParams = [
            'torrent' => $torrent
        ];
        
        return $this->view('XBTTracker:Torrent\Delete', 'xbt_torrent_delete', $viewParams);
    }
    
    /**
     * Handle torrent deletion
     */
    public function actionDeleteConfirm(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertCanView();
        
        $torrentId = $params->get('torrent_id');
        $torrent = $this->assertDeletableTorrent($torrentId);
        
        $torrentService = $this->service('XBTTracker:Torrent\Deleter', $torrent);
        $torrentService->delete();
        
        return $this->redirect($this->buildLink('torrents'));
    }
    
    /**
     * Thank torrent uploader
     */
    public function actionThanks(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertCanView();
        $this->assertCanDownload();
        
        $torrentId = $params->get('torrent_id');
        $torrent = $this->assertViewableTorrent($torrentId);
        
        $visitor = \XF::visitor();
        
        // Check if already thanked
        if ($this->getTorrentRepo()->hasThankedTorrent($torrentId, $visitor->user_id)) {
            return $this->redirect($this->buildLink('torrents/view', $torrent));
        }
        
        // Record thank you
        $this->getTorrentRepo()->thankTorrent($torrentId, $visitor->user_id);
        
        return $this->redirect($this->buildLink('torrents/view', $torrent));
    }
    
    /**
     * Assert can view torrents
     */
    protected function assertCanView()
    {
        if (!\XF::visitor()->hasPermission('xbtTracker', 'view')) {
            throw $this->exception($this->noPermission());
        }
    }
    
    /**
     * Assert can download torrents
     */
    protected function assertCanDownload()
    {
        if (!\XF::visitor()->hasPermission('xbtTracker', 'download')) {
            throw $this->exception($this->noPermission());
        }
    }
    
    /**
     * Assert can upload torrents
     */
    protected function assertCanUpload()
    {
        if (!\XF::visitor()->hasPermission('xbtTracker', 'upload')) {
            throw $this->exception($this->noPermission());
        }
    }
    
    /**
     * Assert torrent is viewable
     */
    protected function assertViewableTorrent($torrentId)
    {
        $torrent = $this->em()->find('XBTTracker:Torrent', $torrentId);
        if (!$torrent) {
            throw $this->exception($this->notFound(\XF::phrase('xbt_requested_torrent_not_found')));
        }
        
        return $torrent;
    }
    
    /**
     * Assert torrent is editable
     */
    protected function assertEditableTorrent($torrentId)
    {
        $torrent = $this->assertViewableTorrent($torrentId);
        $visitor = \XF::visitor();
        
        // Allow edit if moderator
        if ($visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
            return $torrent;
        }
        
        // Allow edit if owner
        if ($visitor->user_id == $torrent->user_id && $visitor->hasPermission('xbtTracker', 'edit')) {
            return $torrent;
        }
        
        throw $this->exception($this->noPermission());
    }
    
    /**
     * Assert torrent is deletable
     */
    protected function assertDeletableTorrent($torrentId)
    {
        $torrent = $this->assertViewableTorrent($torrentId);
        $visitor = \XF::visitor();
        
        // Allow delete if moderator
        if ($visitor->hasPermission('xbtTracker', 'moderateTorrents')) {
            return $torrent;
        }
        
        // Allow delete if owner
        if ($visitor->user_id == $torrent->user_id && $visitor->hasPermission('xbtTracker', 'delete')) {
            return $torrent;
        }
        
        throw $this->exception($this->noPermission());
    }
    
    /**
     * Get torrent repository
     */
    protected function getTorrentRepo()
    {
        return $this->repository('XBTTracker:Torrent');
    }
    
    /**
     * Get category repository
     */
    protected function getCategoryRepo()
    {
        return $this->repository('XBTTracker:Category');
    }
    
    /**
     * Get user stats repository
     */
    protected function getUserStatsRepo()
    {
        return $this->repository('XBTTracker:UserStats');
    }
}