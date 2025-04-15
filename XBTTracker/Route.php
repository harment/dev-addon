<?php

namespace XBTTracker;

use XF\Mvc\RouteBuilderInterface;
use XF\Mvc\Router;

class Route implements RouteBuilderInterface
{
    /**
     * Build a link to a specific route
     *
     * @param string $prefix Route prefix
     * @param string $route Route name
     * @param string $buildClass Class to build the link
     * @param mixed $data Data to build the link with
     * @param array $parameters Additional parameters
     * @return string|null
     */
    public function buildLink($prefix, $route, $buildClass, $data, array $parameters)
    {
        return null;
    }
    
    /**
     * Build routes for the add-on
     *
     * @param string $prefix Route prefix
     * @param RouteBuilderInterface $builder Route builder
     * @return void
     */
    public function build($prefix, RouteBuilderInterface $builder)
    {
        if ($prefix == 'public') {
            $this->buildPublicRoutes($builder);
        } 
        else if ($prefix == 'admin') {
            $this->buildAdminRoutes($builder);
        }
    }

    /**
     * Build public routes
     *
     * @param RouteBuilderInterface $builder Route builder
     * @return void
     */
    protected function buildPublicRoutes(RouteBuilderInterface $builder)
    {
        // Torrent listing and details
        $builder->addRoute('', 'torrents', 'XBTTracker:Torrent', 'List');
        $builder->addRoute('view/{info_hash}', 'torrents/view/{info_hash}', 'XBTTracker:Torrent', 'View');
        $builder->addRoute('download/{info_hash}', 'torrents/download/{info_hash}', 'XBTTracker:Torrent', 'Download');
        
        // Torrent management
        $builder->addRoute('upload', 'torrents/upload', 'XBTTracker:Torrent', 'Upload');
        $builder->addRoute('upload-save', 'torrents/upload-save', 'XBTTracker:Torrent', 'UploadSave');
        $builder->addRoute('edit/{info_hash}', 'torrents/edit/{info_hash}', 'XBTTracker:Torrent', 'Edit');
        $builder->addRoute('edit-save/{info_hash}', 'torrents/edit-save/{info_hash}', 'XBTTracker:Torrent', 'EditSave');
        $builder->addRoute('delete/{info_hash}', 'torrents/delete/{info_hash}', 'XBTTracker:Torrent', 'Delete');
        $builder->addRoute('delete-confirm/{info_hash}', 'torrents/delete-confirm/{info_hash}', 'XBTTracker:Torrent', 'DeleteConfirm');
        
        // User torrent interaction
        $builder->addRoute('thanks/{info_hash}', 'torrents/thanks/{info_hash}', 'XBTTracker:Torrent', 'Thanks');
        $builder->addRoute('favorite/add/{info_hash}', 'torrents/favorite/add/{info_hash}', 'XBTTracker:Favorite', 'Add');
        $builder->addRoute('favorite/remove/{info_hash}', 'torrents/favorite/remove/{info_hash}', 'XBTTracker:Favorite', 'Remove');
        
        // TMDB integration
        $builder->addRoute('tmdb/search', 'torrents/tmdb/search', 'XBTTracker:Tmdb', 'Search');
        $builder->addRoute('tmdb/info/{tmdb_id}', 'torrents/tmdb/info/{tmdb_id}', 'XBTTracker:Tmdb', 'Info');
        
        // User profile pages
        $builder->addRoute('user-torrents', 'torrents/user-torrents', 'XBTTracker:UserTorrents', 'List');
        $builder->addRoute('bonus-points', 'torrents/bonus-points', 'XBTTracker:BonusPoints', 'View');
        $builder->addRoute('bonus-points/redeem', 'torrents/bonus-points/redeem', 'XBTTracker:BonusPoints', 'Redeem');
        $builder->addRoute('warnings', 'torrents/warnings', 'XBTTracker:Warnings', 'View');
        $builder->addRoute('warnings/remove/{warning_id}', 'torrents/warnings/remove/{warning_id}', 'XBTTracker:Warnings', 'Remove');
        
        // Category browse
        $builder->addRoute('category/{category_id}', 'torrents/category/{category_id}', 'XBTTracker:Category', 'View');
        
        // Search
        $builder->addRoute('search', 'torrents/search', 'XBTTracker:Search', 'Index');
        $builder->addRoute('search/advanced', 'torrents/search/advanced', 'XBTTracker:Search', 'Advanced');
    }

    /**
     * Build admin routes
     *
     * @param RouteBuilderInterface $builder Route builder
     * @return void
     */
    protected function buildAdminRoutes(RouteBuilderInterface $builder)
    {
        // Dashboard
        $builder->addRoute('', 'torrents', 'XBTTracker:Admin', 'Dashboard');
        
        // Torrent management
        $builder->addRoute('torrents', 'torrents/torrents', 'XBTTracker:Admin\Torrent', 'List');
        $builder->addRoute('torrent/view/{info_hash}', 'torrents/torrent/view/{info_hash}', 'XBTTracker:Admin\Torrent', 'View');
        $builder->addRoute('torrent/add', 'torrents/torrent/add', 'XBTTracker:Admin\Torrent', 'Add');
        $builder->addRoute('torrent/add-save', 'torrents/torrent/add-save', 'XBTTracker:Admin\Torrent', 'AddSave');
        $builder->addRoute('torrent/edit/{info_hash}', 'torrents/torrent/edit/{info_hash}', 'XBTTracker:Admin\Torrent', 'Edit');
        $builder->addRoute('torrent/edit-save/{info_hash}', 'torrents/torrent/edit-save/{info_hash}', 'XBTTracker:Admin\Torrent', 'EditSave');
        $builder->addRoute('torrent/delete/{info_hash}', 'torrents/torrent/delete/{info_hash}', 'XBTTracker:Admin\Torrent', 'Delete');
        $builder->addRoute('torrent/delete-confirm/{info_hash}', 'torrents/torrent/delete-confirm/{info_hash}', 'XBTTracker:Admin\Torrent', 'DeleteConfirm');
        $builder->addRoute('torrent/bulk-action', 'torrents/torrent/bulk-action', 'XBTTracker:Admin\Torrent', 'BulkAction');
        
        // Category management
        $builder->addRoute('categories', 'torrents/categories', 'XBTTracker:Admin\Category', 'List');
        $builder->addRoute('category/add', 'torrents/category/add', 'XBTTracker:Admin\Category', 'Add');
        $builder->addRoute('category/add-save', 'torrents/category/add-save', 'XBTTracker:Admin\Category', 'AddSave');
        $builder->addRoute('category/edit/{category_id}', 'torrents/category/edit/{category_id}', 'XBTTracker:Admin\Category', 'Edit');
        $builder->addRoute('category/edit-save/{category_id}', 'torrents/category/edit-save/{category_id}', 'XBTTracker:Admin\Category', 'EditSave');
        $builder->addRoute('category/delete/{category_id}', 'torrents/category/delete/{category_id}', 'XBTTracker:Admin\Category', 'Delete');
        $builder->addRoute('category/delete-confirm/{category_id}', 'torrents/category/delete-confirm/{category_id}', 'XBTTracker:Admin\Category', 'DeleteConfirm');
        $builder->addRoute('category/move', 'torrents/category/move', 'XBTTracker:Admin\Category', 'Move');
        
        // User management
        $builder->addRoute('users', 'torrents/users', 'XBTTracker:Admin\User', 'List');
        $builder->addRoute('user/details/{user_id}', 'torrents/user/details/{user_id}', 'XBTTracker:Admin\User', 'Details');
        $builder->addRoute('user/add', 'torrents/user/add', 'XBTTracker:Admin\User', 'Add');
        $builder->addRoute('user/add-save', 'torrents/user/add-save', 'XBTTracker:Admin\User', 'AddSave');
        $builder->addRoute('user/edit/{user_id}', 'torrents/user/edit/{user_id}', 'XBTTracker:Admin\User', 'Edit');
        $builder->addRoute('user/edit-save/{user_id}', 'torrents/user/edit-save/{user_id}', 'XBTTracker:Admin\User', 'EditSave');
        $builder->addRoute('user/delete/{user_id}', 'torrents/user/delete/{user_id}', 'XBTTracker:Admin\User', 'Delete');
        $builder->addRoute('user/bulk-action', 'torrents/user/bulk-action', 'XBTTracker:Admin\User', 'BulkAction');
        
        // Bonus points management
        $builder->addRoute('bonus-points', 'torrents/bonus-points', 'XBTTracker:Admin\BonusPoints', 'List');
        $builder->addRoute('bonus-points/all', 'torrents/bonus-points/all', 'XBTTracker:Admin\BonusPoints', 'All');
        $builder->addRoute('bonus-points/award', 'torrents/bonus-points/award', 'XBTTracker:Admin\BonusPoints', 'Award');
        $builder->addRoute('bonus-points/award-all', 'torrents/bonus-points/award-all', 'XBTTracker:Admin\BonusPoints', 'AwardAll');
        $builder->addRoute('bonus-points/award-by-ratio', 'torrents/bonus-points/award-by-ratio', 'XBTTracker:Admin\BonusPoints', 'AwardByRatio');
        $builder->addRoute('bonus-points/adjust/{user_id}', 'torrents/bonus-points/adjust/{user_id}', 'XBTTracker:Admin\BonusPoints', 'Adjust');
        $builder->addRoute('bonus-points/history/{user_id}', 'torrents/bonus-points/history/{user_id}', 'XBTTracker:Admin\BonusPoints', 'History');
        $builder->addRoute('bonus-points/log', 'torrents/bonus-points/log', 'XBTTracker:Admin\BonusPoints', 'Log');
        $builder->addRoute('bonus-points/reset-all', 'torrents/bonus-points/reset-all', 'XBTTracker:Admin\BonusPoints', 'ResetAll');
        
        // Warnings management
        $builder->addRoute('warnings', 'torrents/warnings', 'XBTTracker:Admin\Warnings', 'List');
        $builder->addRoute('warning/issue', 'torrents/warning/issue', 'XBTTracker:Admin\Warnings', 'Issue');
        $builder->addRoute('warning/details/{warning_id}', 'torrents/warning/details/{warning_id}', 'XBTTracker:Admin\Warnings', 'Details');
        $builder->addRoute('warning/remove/{warning_id}', 'torrents/warning/remove/{warning_id}', 'XBTTracker:Admin\Warnings', 'Remove');
        $builder->addRoute('warning/delete/{warning_id}', 'torrents/warning/delete/{warning_id}', 'XBTTracker:Admin\Warnings', 'Delete');
        $builder->addRoute('warning/bulk-action', 'torrents/warning/bulk-action', 'XBTTracker:Admin\Warnings', 'BulkAction');
        
        // Announcements management
        $builder->addRoute('announcements', 'torrents/announcements', 'XBTTracker:Admin\Announcements', 'List');
        $builder->addRoute('announcement/add', 'torrents/announcement/add', 'XBTTracker:Admin\Announcements', 'Add');
        $builder->addRoute('announcement/details/{announcement_id}', 'torrents/announcement/details/{announcement_id}', 'XBTTracker:Admin\Announcements', 'Details');
        $builder->addRoute('announcement/edit/{announcement_id}', 'torrents/announcement/edit/{announcement_id}', 'XBTTracker:Admin\Announcements', 'Edit');
        $builder->addRoute('announcement/delete/{announcement_id}', 'torrents/announcement/delete/{announcement_id}', 'XBTTracker:Admin\Announcements', 'Delete');
        
        // System logs
        $builder->addRoute('logs', 'torrents/logs', 'XBTTracker:Admin\Logs', 'List');
        $builder->addRoute('logs/export', 'torrents/logs/export', 'XBTTracker:Admin\Logs', 'Export');
        $builder->addRoute('logs/clear', 'torrents/logs/clear', 'XBTTracker:Admin\Logs', 'Clear');
        $builder->addRoute('logs/test-tmdb-api', 'torrents/logs/test-tmdb-api', 'XBTTracker:Admin\Logs', 'TestTmdbApi');
        $builder->addRoute('logs/test-mail-settings', 'torrents/logs/test-mail-settings', 'XBTTracker:Admin\Logs', 'TestMailSettings');
        
        // Settings
        $builder->addRoute('settings', 'torrents/settings', 'XBTTracker:Admin\Settings', 'Index');
        $builder->addRoute('settings/update', 'torrents/settings/update', 'XBTTracker:Admin\Settings', 'Update');
        $builder->addRoute('settings/create-backup', 'torrents/settings/create-backup', 'XBTTracker:Admin\Settings', 'CreateBackup');
        $builder->addRoute('settings/restore-backup', 'torrents/settings/restore-backup', 'XBTTracker:Admin\Settings', 'RestoreBackup');
    }
}