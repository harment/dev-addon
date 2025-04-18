<?php
// src/addons/XBTTracker/Controller/Admin.php
namespace Harment\XBTTracker\Controller;

use XF\Mvc\ParameterBag;

class Admin extends \XF\Mvc\Controller
{
    /**
     * Dashboard for XBT Tracker
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionIndex()
    {
        $trackerStats = $this->getTorrentRepo()->getTorrentStats();
        
        // Get tracker status
        $status = 'offline';
        $announceUrl = $this->options()->xbtTrackerAnnounceURL;
        
        if ($announceUrl) {
            $parsedUrl = parse_url($announceUrl);
            if (isset($parsedUrl['host']) && isset($parsedUrl['port'])) {
                $host = $parsedUrl['host'];
                $port = $parsedUrl['port'];
                $connection = @fsockopen($host, $port, $errno, $errstr, 5);
                if ($connection) {
                    $status = 'online';
                    fclose($connection);
                }
            }
        }
        
        $viewParams = [
            'trackerStats' => $trackerStats,
            'trackerStatus' => $status,
            'announceUrl' => $announceUrl
        ];
        
        return $this->view('XBTTracker:Admin\Dashboard', 'xbt_admin_dashboard', $viewParams);
    }
    
    /**
     * List torrents in admin panel
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionTorrents()
    {
        $page = $this->filterPage();
        $perPage = 50;
        
        $categoryId = $this->filter('category_id', 'uint');
        $search = $this->filter('search', 'str');
        $userId = $this->filter('user_id', 'uint');
        $sort = $this->filter('sort', 'str', 'date');
        $order = $this->filter('order', 'str', 'desc');
        
        $finder = $this->getTorrentRepo()->findTorrentsForList();
        
        if ($categoryId) {
            $finder->where('category_id', $categoryId);
        }
        
        if ($search) {
            $finder->where('title', 'LIKE', '%' . $finder->escapeLike($search) . '%');
        }
        
        if ($userId) {
            $finder->where('user_id', $userId);
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
            
        $viewParams = [
            'torrents' => $finder->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $finder->total(),
            'categories' => $categories,
            'categoryId' => $categoryId,
            'search' => $search,
            'userId' => $userId,
            'sort' => $sort,
            'order' => $order
        ];
        
        return $this->view('XBTTracker:Admin\Torrents', 'xbt_admin_torrents', $viewParams);
    }
    
    /**
     * Edit a torrent in admin panel
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionTorrentEdit(ParameterBag $params)
    {
        $torrent = $this->assertTorrentExists($params->torrent_id);
        
        if ($this->isPost()) {
            $title = $this->filter('title', 'str');
            $description = $this->filter('description', 'str');
            $categoryId = $this->filter('category_id', 'uint');
            $videoQuality = $this->filter('video_quality', 'str');
            $audioFormat = $this->filter('audio_format', 'str');
            $audioChannels = $this->filter('audio_channels', 'str');
            $tmdbId = $this->filter('tmdb_id', 'uint');
            $isFreeleech = $this->filter('is_freeleech', 'bool');
            
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
                @var \XBTTracker\Service\Torrent\PosterSaver $posterSaver */
                $posterSaver = $this->service('XBTTracker:Torrent\PosterSaver');
                $posterSaver->savePoster($torrent, $posterFile);
            }
            
            $torrent->save();
            
            return $this->redirect($this->buildLink('torrents/torrents'));
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
        
        return $this->view('XBTTracker:Admin\TorrentEdit', 'xbt_admin_torrent_edit', $viewParams);
    }
    
    /**
     * Delete a torrent from admin panel
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionTorrentDelete(ParameterBag $params)
    {
        $torrent = $this->assertTorrentExists($params->torrent_id);
        
        if ($this->isPost()) {
            /** @var \XBTTracker\Service\Torrent\Deleter $deleter */
            $deleter = $this->service('XBTTracker:Torrent\Deleter');
            $deleter->deleteTorrent($torrent);
            
            return $this->redirect($this->buildLink('torrents/torrents'));
        }
        
        $viewParams = [
            'torrent' => $torrent
        ];
        
        return $this->view('XBTTracker:Admin\TorrentDelete', 'xbt_admin_torrent_delete', $viewParams);
    }
    
    /**
     * List categories in admin panel
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionCategories()
    {
        $categories = $this->finder('XBTTracker:Category')
            ->with('Parent')
            ->order('parent_id', 'asc')
            ->order('display_order', 'asc')
            ->fetch();
            
        $viewParams = [
            'categories' => $categories
        ];
        
        return $this->view('XBTTracker:Admin\Categories', 'xbt_admin_categories', $viewParams);
    }
    
    /**
     * Add a new category
     *
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionCategoryAdd()
    {
        if ($this->isPost()) {
            $title = $this->filter('title', 'str');
            $description = $this->filter('description', 'str');
            $parentId = $this->filter('parent_id', 'uint');
            $displayOrder = $this->filter('display_order', 'uint');
            $nodeId = $this->filter('node_id', 'uint');
            
            /** @var \XBTTracker\Entity\Category $category */
            $category = $this->em()->create('XBTTracker:Category');
            $category->title = $title;
            $category->description = $description;
            $category->parent_id = $parentId;
            $category->display_order = $displayOrder;
            $category->node_id = $nodeId;
            $category->save();
            
            return $this->redirect($this->buildLink('torrents/categories'));
        }
        
        $categories = $this->finder('XBTTracker:Category')
            ->order('display_order')
            ->fetch();
            
        $nodes = $this->finder('XF:Node')
            ->with('NodeType')
            ->where('node_type_id', 'Forum')
            ->order('lft')
            ->fetch();
        
        $viewParams = [
            'categories' => $categories,
            'nodes' => $nodes
        ];
        
        return $this->view('XBTTracker:Admin\CategoryAdd', 'xbt_admin_category_add', $viewParams);
    }
    
    /**
     * Edit an existing category
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionCategoryEdit(ParameterBag $params)
    {
        $category = $this->assertCategoryExists($params->category_id);
        
        if ($this->isPost()) {
            $title = $this->filter('title', 'str');
            $description = $this->filter('description', 'str');
            $parentId = $this->filter('parent_id', 'uint');
            $displayOrder = $this->filter('display_order', 'uint');
            $nodeId = $this->filter('node_id', 'uint');
            
            // Make sure we're not making this category a child of itself
            if ($parentId == $category->category_id) {
                return $this->error(\XF::phrase('xbt_invalid_parent_category'));
            }
            
            $category->title = $title;
            $category->description = $description;
            $category->parent_id = $parentId;
            $category->display_order = $displayOrder;
            $category->node_id = $nodeId;
            $category->save();
            
            return $this->redirect($this->buildLink('torrents/categories'));
        }
        
        $categories = $this->finder('XBTTracker:Category')
            ->where('category_id', '!=', $category->category_id)  // Don't show self as parent option
            ->order('display_order')
            ->fetch();
            
        $nodes = $this->finder('XF:Node')
            ->with('NodeType')
            ->where('node_type_id', 'Forum')
            ->order('lft')
            ->fetch();
        
        $viewParams = [
            'category' => $category,
            'categories' => $categories,
            'nodes' => $nodes
        ];
        
        return $this->view('XBTTracker:Admin\CategoryEdit', 'xbt_admin_category_edit', $viewParams);
    }
    
    /**
     * Delete a category
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionCategoryDelete(ParameterBag $params)
    {
        $category = $this->assertCategoryExists($params->category_id);
        
        if ($this->isPost()) {
            // Check if there are any torrents in this category
            $torrentCount = $this->finder('XBTTracker:Torrent')
                ->where('category_id', $category->category_id)
                ->total();
                
            if ($torrentCount > 0) {
                return $this->error(\XF::phrase('xbt_cannot_delete_category_with_torrents'));
            }
            
            $category->delete();
            
            return $this->redirect($this->buildLink('torrents/categories'));
        }
        
        $viewParams = [
            'category' => $category
        ];
        
        return $this->view('XBTTracker:Admin\CategoryDelete', 'xbt_admin_category_delete', $viewParams);
    }
    
    /**
     * User stats management
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionUserStats()
    {
        $page = $this->filterPage();
        $perPage = 50;
        
        $search = $this->filter('search', 'str');
        $sort = $this->filter('sort', 'str', 'ratio');
        $order = $this->filter('order', 'str', 'desc');
        
        $finder = $this->finder('XBTTracker:UserStats')
            ->with('User')
            ->where('User.username', 'LIKE', $search ? '%' . $finder->escapeLike($search) . '%' : '%');
        
        switch ($sort) {
            case 'username':
                $finder->order('User.username', $order);
                break;
            case 'uploaded':
                $finder->order('uploaded', $order);
                break;
            case 'downloaded':
                $finder->order('downloaded', $order);
                break;
            case 'bonus_points':
                $finder->order('bonus_points', $order);
                break;
            case 'warnings':
                $finder->order('warnings', $order);
                break;
            case 'ratio':
            default:
                if ($order == 'asc') {
                    // Special handling for ratio sorting in ascending order
                    // First sort by users with 0 download (ratio = ∞)
                    $finder->whereOr([
                        ['downloaded', '>', 0],
                        ['uploaded', '=', 0]
                    ]);
                    $finder->order('uploaded / downloaded', 'ASC');
                } else {
                    // Sort by highest ratio first, with 0 downloads last
                    $finder->order('IF(downloaded = 0, IF(uploaded > 0, 999999999, 0), uploaded / downloaded)', 'DESC');
                }
        }
        
        $finder->limitByPage($page, $perPage);
        
        $viewParams = [
            'userStats' => $finder->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $finder->total(),
            'search' => $search,
            'sort' => $sort,
            'order' => $order
        ];
        
        return $this->view('XBTTracker:Admin\UserStats', 'xbt_admin_user_stats', $viewParams);
    }
    
    /**
     * Edit user stats
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionUserStatsEdit(ParameterBag $params)
    {
        $userStats = $this->assertUserStatsExists($params->user_id);
        
        if ($this->isPost()) {
            $uploaded = $this->filter('uploaded', 'uint');
            $downloaded = $this->filter('downloaded', 'uint');
            $bonusPoints = $this->filter('bonus_points', 'uint');
            $warnings = $this->filter('warnings', 'uint');
            $passkey = $this->filter('passkey', 'str');
            
            // Validate passkey format
            if ($passkey && strlen($passkey) != 32) {
                return $this->error(\XF::phrase('xbt_invalid_passkey_format'));
            }
            
            $userStats->uploaded = $uploaded;
            $userStats->downloaded = $downloaded;
            $userStats->bonus_points = $bonusPoints;
            $userStats->warnings = $warnings;
            
            if ($passkey) {
                $userStats->passkey = $passkey;
            }
            
            $userStats->save();
            
            return $this->redirect($this->buildLink('torrents/user-stats'));
        }
        
        $viewParams = [
            'userStats' => $userStats
        ];
        
        return $this->view('XBTTracker:Admin\UserStatsEdit', 'xbt_admin_user_stats_edit', $viewParams);
    }
    
    /**
     * Reset a user's passkey
     * 
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionUserStatsResetPasskey(ParameterBag $params)
    {
        $userStats = $this->assertUserStatsExists($params->user_id);
        
        if ($this->isPost()) {
            $userStats->generatePasskey();
            $userStats->save();
            
            return $this->redirect($this->buildLink('torrents/user-stats'));
        }
        
        $viewParams = [
            'userStats' => $userStats
        ];
        
        return $this->view('XBTTracker:Admin\UserStatsResetPasskey', 'xbt_admin_user_stats_reset_passkey', $viewParams);
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
     * Assert that a category exists
     *
     * @param int $id
     * @return \XBTTracker\Entity\Category
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCategoryExists($id)
    {
        $category = $this->finder('XBTTracker:Category')
            ->where('category_id', $id)
            ->fetchOne();
            
        if (!$category) {
            throw $this->exception($this->notFound(\XF::phrase('xbt_requested_category_not_found')));
        }
        
        return $category;
    }
    
    /**
     * Assert that user stats exist
     *
     * @param int $userId
     * @return \XBTTracker\Entity\UserStats
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertUserStatsExists($userId)
    {
        $userStats = $this->finder('XBTTracker:UserStats')
            ->with('User')
            ->where('user_id', $userId)
            ->fetchOne();
            
        if (!$userStats) {
            throw $this->exception($this->notFound(\XF::phrase('xbt_requested_user_stats_not_found')));
        }
        
        return $userStats;
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