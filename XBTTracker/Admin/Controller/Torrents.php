<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Torrents extends AbstractController
{
    /**
     * Display admin dashboard
     */
    public function actionIndex()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        // Get tracker stats
        $trackerService = $this->service('Harment\XBTTracker:Tracker');
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
            'topUsers' => $topUsers,
            'trackerStatus' => [
                'connected' => $trackerService->isConnected(),
                'database' => $this->options()->xbtTrackerDbName,
                'version' => 'XBT Tracker ' . $this->app()->addOnManager()->getById('Harment/XBTTracker')->version_string
            ],
            'announceUrl' => $this->options()->xbtTrackerAnnounceUrl
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Dashboard', 'harment_xbttracker_admin_dashboard', $viewParams);
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
        $filters = $this->filter([
            'category_id' => 'uint',
            'q' => 'str',
            'user_id' => 'uint',
            'is_freeleech' => 'uint:0,1',
            'has_seeders' => 'str',
            'sort' => 'str',
            'order' => 'str'
        ]);
        
        // Set defaults
        if (empty($filters['sort'])) {
            $filters['sort'] = 'creation_date';
        }
        if (empty($filters['order'])) {
            $filters['order'] = 'desc';
        }
        
        // Get torrent finder
        $torrentFinder = $this->getTorrentRepo()->findTorrentsForList();
        
        // Apply filters
        if ($filters['category_id']) {
            $torrentFinder->where('category_id', $filters['category_id']);
        }
        
        if ($filters['q']) {
            $torrentFinder->where('title', 'LIKE', '%' . $torrentFinder->escapeLike($filters['q']) . '%');
        }
        
        if ($filters['user_id']) {
            $torrentFinder->where('user_id', $filters['user_id']);
        }
        
        if ($filters['is_freeleech'] !== null) {
            $torrentFinder->where('is_freeleech', $filters['is_freeleech']);
        }
        
        if ($filters['has_seeders'] !== '') {
            if ($filters['has_seeders'] == 'yes') {
                $torrentFinder->where('seeders', '>', 0);
            } else if ($filters['has_seeders'] == 'no') {
                $torrentFinder->where('seeders', 0);
            }
        }
        
        // Apply sort order
        switch ($filters['sort']) {
            case 'title':
                $torrentFinder->order('title', $filters['order']);
                break;
            case 'size':
                $torrentFinder->order('size', $filters['order']);
                break;
            case 'seeders':
                $torrentFinder->order('seeders', $filters['order']);
                break;
            case 'leechers':
                $torrentFinder->order('leechers', $filters['order']);
                break;
            case 'completed':
                $torrentFinder->order('completed', $filters['order']);
                break;
            case 'user':
                $torrentFinder->order('User.username', $filters['order']);
                break;
            case 'creation_date':
            default:
                $torrentFinder->order('creation_date', $filters['order']);
                break;
        }
        
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
            'categoryId' => $filters['category_id'],
            'searchQuery' => $filters['q'],
            'userId' => $filters['user_id'],
            'isFreeleech' => $filters['is_freeleech'],
            'hasSeeders' => $filters['has_seeders'],
            'sort' => $filters['sort'],
            'order' => $filters['order']
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Torrent\List', 'harment_xbttracker_admin_torrents', $viewParams);
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
            'categories' => $categories,
            'videoQualities' => $this->getVideoQualityOptions(),
            'audioFormats' => $this->getAudioFormatOptions(),
            'audioChannels' => $this->getAudioChannelOptions()
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Torrent\Edit', 'harment_xbttracker_admin_torrent_edit', $viewParams);
    }
    
    /**
     * Handle torrent edit
     */
    public function actionSave(ParameterBag $params)
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
            $errors[] = \XF::phrase('harment_xbttracker_torrent_title_required');
        }
        
        if (!$input['category_id']) {
            $errors[] = \XF::phrase('harment_xbttracker_torrent_category_required');
        }
        
        if ($errors) {
            return $this->error($errors);
        }
        
        // Create the editor service
        $torrentService = $this->service('Harment\XBTTracker:Torrent\Editor', $torrent);
        
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
        
        // Log the edit
        $this->app()->logger()->info(
            sprintf('Torrent %d (%s) edited by %s',
                $torrent->torrent_id,
                $torrent->title,
                \XF::visitor()->username
            )
        );
        
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
        
        return $this->view('Harment\XBTTracker:Admin\Torrent\Delete', 'harment_xbttracker_admin_torrent_delete', $viewParams);
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
        
        $torrentTitle = $torrent->title;
        
        $torrentService = $this->service('Harment\XBTTracker:Torrent\Deleter', $torrent);
        $torrentService->delete();
        
        // Log the deletion
        $this->app()->logger()->info(
            sprintf('Torrent %d (%s) deleted by %s',
                $torrentId,
                $torrentTitle,
                \XF::visitor()->username
            )
        );
        
        return $this->redirect($this->buildLink('tracker/torrents'));
    }
    
    /**
     * Bulk actions on torrents
     */
    public function actionBulk()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $torrentIds = $this->filter('torrent_ids', 'array-uint');
        if (empty($torrentIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_torrents_selected'));
        }
        
        $action = $this->filter('action', 'str');
        
        switch ($action) {
            case 'delete':
                return $this->rerouteController(__CLASS__, 'bulkDelete', [
                    'torrent_ids' => $torrentIds
                ]);
                
            case 'freeleech':
                return $this->rerouteController(__CLASS__, 'bulkFreeleech', [
                    'torrent_ids' => $torrentIds,
                    'freeleech' => 1
                ]);
                
            case 'remove_freeleech':
                return $this->rerouteController(__CLASS__, 'bulkFreeleech', [
                    'torrent_ids' => $torrentIds,
                    'freeleech' => 0
                ]);
                
            case 'move':
                $categoryId = $this->filter('category_id', 'uint');
                if (!$categoryId) {
                    return $this->error(\XF::phrase('harment_xbttracker_select_category_to_move'));
                }
                
                return $this->rerouteController(__CLASS__, 'bulkMove', [
                    'torrent_ids' => $torrentIds,
                    'category_id' => $categoryId
                ]);
                
            default:
                return $this->error(\XF::phrase('harment_xbttracker_invalid_bulk_action'));
        }
    }
    
    /**
     * Bulk delete torrents
     */
    public function actionBulkDelete()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $torrentIds = $this->filter('torrent_ids', 'array-uint');
        if (empty($torrentIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_torrents_selected'));
        }
        
        if ($this->request->exists('confirm')) {
            $torrents = $this->finder('Harment\XBTTracker:Torrent')
                ->whereIds($torrentIds)
                ->fetch();
                
            $deleter = $this->service('Harment\XBTTracker:Torrent\BulkDeleter', $torrents);
            $deleter->delete();
            
            return $this->redirect($this->buildLink('tracker/torrents'));
        } else {
            $viewParams = [
                'torrentIds' => $torrentIds,
                'torrentCount' => count($torrentIds)
            ];
            
            return $this->view('Harment\XBTTracker:Admin\Torrent\BulkDelete', 'harment_xbttracker_admin_torrent_bulk_delete', $viewParams);
        }
    }
    
    /**
     * Bulk set freeleech status
     */
    public function actionBulkFreeleech()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $torrentIds = $this->filter('torrent_ids', 'array-uint');
        if (empty($torrentIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_torrents_selected'));
        }
        
        $isFreeleech = $this->filter('freeleech', 'bool');
        
        $this->db()->update(
            'xf_xbt_torrents',
            ['is_freeleech' => $isFreeleech ? 1 : 0],
            'torrent_id IN (' . $this->db()->quote($torrentIds) . ')'
        );
        
        // Log the action
        $action = $isFreeleech ? 'set as freeleech' : 'removed from freeleech';
        $this->app()->logger()->info(
            sprintf('%d torrents %s by %s',
                count($torrentIds),
                $action,
                \XF::visitor()->username
            )
        );
        
        return $this->redirect($this->buildLink('tracker/torrents'));
    }
    
    /**
     * Bulk move torrents to category
     */
    public function actionBulkMove()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $torrentIds = $this->filter('torrent_ids', 'array-uint');
        if (empty($torrentIds)) {
            return $this->error(\XF::phrase('harment_xbttracker_no_torrents_selected'));
        }
        
        $categoryId = $this->filter('category_id', 'uint');
        if (!$categoryId) {
            return $this->error(\XF::phrase('harment_xbttracker_select_category_to_move'));
        }
        
        // Verify category exists
        $category = $this->em()->find('Harment\XBTTracker:Category', $categoryId);
        if (!$category) {
            return $this->error(\XF::phrase('harment_xbttracker_requested_category_not_found'));
        }
        
        $this->db()->update(
            'xf_xbt_torrents',
            ['category_id' => $categoryId],
            'torrent_id IN (' . $this->db()->quote($torrentIds) . ')'
        );
        
        // Log the action
        $this->app()->logger()->info(
            sprintf('%d torrents moved to category "%s" by %s',
                count($torrentIds),
                $category->title,
                \XF::visitor()->username
            )
        );
        
        return $this->redirect($this->buildLink('tracker/torrents'));
    }
    
    /**
     * Assert torrent exists
     */
    protected function assertTorrentExists($torrentId)
    {
        $torrent = $this->em()->find('Harment\XBTTracker:Torrent', $torrentId);
        if (!$torrent) {
            throw $this->exception($this->notFound(\XF::phrase('harment_xbttracker_requested_torrent_not_found')));
        }
        
        return $torrent;
    }
    
    /**
     * Get torrent repository
     */
    protected function getTorrentRepo()
    {
        return $this->repository('Harment\XBTTracker:Torrent');
    }
    
    /**
     * Get category repository
     */
    protected function getCategoryRepo()
    {
        return $this->repository('Harment\XBTTracker:Category');
    }
    
    /**
     * Get user stats repository
     */
    protected function getUserStatsRepo()
    {
        return $this->repository('Harment\XBTTracker:UserStats');
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