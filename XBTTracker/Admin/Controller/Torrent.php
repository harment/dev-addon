<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Torrent extends AbstractController
{
    /**
     * Display admin dashboard
     */
    public function actionDashboard()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        // Get tracker stats
        $trackerService = $this->app()->get('xbt.tracker.service');
        $stats = $trackerService->getStats();
        
        // Get latest torrents
        $latestTorrents = $this->getTorrentRepo()
            ->findTorrentsForList()
            ->order('creation_date', 'DESC')
            ->limit(10)
            ->fetch();
        
        // Get top users
        $topUsers = $this->getUserStatsRepo()
            ->findUserStatsForList()
            ->order('uploaded', 'DESC')
            ->limit(10)
            ->fetch();
        
        $viewParams = [
            'stats' => $stats,
            'latestTorrents' => $latestTorrents,
            'topUsers' => $topUsers
        ];
        
        return $this->view('XBTTracker:Admin\Dashboard', 'xbt_admin_dashboard', $viewParams);
    }
    
    /**
     * Display torrents list
     */
    public function actionList()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $page = $this->filterPage();
        $perPage = 50;
        
        // Search parameters
        $categoryId = $this->filter('category_id', 'uint');
        $searchQuery = $this->filter('q', 'str');
        $userId = $this->filter('user_id', 'uint');
        
        // Get torrent finder
        $torrentFinder = $this->getTorrentRepo()->getTorrentFinder();
        
        // Apply filters
        if ($categoryId) {
            $torrentFinder->where('category_id', $categoryId);
        }
        
        if ($searchQuery) {
            $torrentFinder->where('title', 'LIKE', '%' . $torrentFinder->escapeLike($searchQuery) . '%');
        }
        
        if ($userId) {
            $torrentFinder->where('user_id', $userId);
        }
        
        // Apply sort order
        $torrentFinder->order('creation_date', 'DESC');
        
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
            'userId' => $userId
        ];
        
        return $this->view('XBTTracker:Admin\Torrent\List', 'xbt_admin_torrents', $viewParams);
    }
    
    /**
     * Edit torrent form
     */
    public function actionEdit(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $torrentId = $params->get('torrent_id');
        $torrent = $this->assertTorrentExists($torrentId);
        
        $categories = $this->getCategoryRepo()->findCategoriesForList()->fetch();
        
        $viewParams = [
            'torrent' => $torrent,
            'categories' => $categories
        ];
        
        return $this->view('XBTTracker:Admin\Torrent\Edit', 'xbt_admin_torrent_edit', $viewParams);
    }
    
    /**
     * Handle torrent edit
     */
    public function actionEditSave(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $torrentId = $params->get('torrent_id');
        $torrent = $this->assertTorrentExists($torrentId);
        
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
        
        // Redirect to torrent list
        return $this->redirect($this->buildLink('tracker/torrents'));
    }
    
    /**
     * Delete torrent confirmation
     */
    public function actionDelete(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $torrentId = $params->get('torrent_id');
        $torrent = $this->assertTorrentExists($torrentId);
        
        $viewParams = [
            'torrent' => $torrent
        ];
        
        return $this->view('XBTTracker:Admin\Torrent\Delete', 'xbt_admin_torrent_delete', $viewParams);
    }
    
    /**
     * Handle torrent deletion
     */
    public function actionDeleteConfirm(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $torrentId = $params->get('torrent_id');
        $torrent = $this->assertTorrentExists($torrentId);
        
        $torrentService = $this->service('XBTTracker:Torrent\Deleter', $torrent);
        $torrentService->delete();
        
        return $this->redirect($this->buildLink('tracker/torrents'));
    }
    
    /**
     * Assert torrent exists
     */
    protected function assertTorrentExists($torrentId)
    {
        $torrent = $this->em()->find('XBTTracker:Torrent', $torrentId);
        if (!$torrent) {
            throw $this->exception($this->notFound(\XF::phrase('xbt_requested_torrent_not_found')));
        }
        
        return $torrent;
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