<?php

namespace Harment\XBTTracker;

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
        // الصفحة الرئيسية (قائمة التورنتات)
        $builder->addRoute('torrents', 'torrents', 'Harment\XBTTracker:Torrent', 'List');
        
        // مسارات التورنتات
        $builder->addRoute('torrents/view/{info_hash}', 'torrents/view/{info_hash}', 'Harment\XBTTracker:Torrent', 'View');
        $builder->addRoute('torrents/download/{info_hash}', 'torrents/download/{info_hash}', 'Harment\XBTTracker:Torrent', 'Download');
        $builder->addRoute('torrents/upload', 'torrents/upload', 'Harment\XBTTracker:Torrent', 'Upload');
        $builder->addRoute('torrents/upload-save', 'torrents/upload-save', 'Harment\XBTTracker:Torrent', 'UploadSave');
        $builder->addRoute('torrents/edit/{info_hash}', 'torrents/edit/{info_hash}', 'Harment\XBTTracker:Torrent', 'Edit');
        $builder->addRoute('torrents/edit-save/{info_hash}', 'torrents/edit-save/{info_hash}', 'Harment\XBTTracker:Torrent', 'EditSave');
        $builder->addRoute('torrents/delete/{info_hash}', 'torrents/delete/{info_hash}', 'Harment\XBTTracker:Torrent', 'Delete');
        $builder->addRoute('torrents/delete-confirm/{info_hash}', 'torrents/delete-confirm/{info_hash}', 'Harment\XBTTracker:Torrent', 'DeleteConfirm');
        
        // مسارات تفاعل المستخدم مع التورنتات
        $builder->addRoute('torrents/thanks/{info_hash}', 'torrents/thanks/{info_hash}', 'Harment\XBTTracker:Torrent', 'Thanks');
        $builder->addRoute('torrents/favorite/add/{info_hash}', 'torrents/favorite/add/{info_hash}', 'Harment\XBTTracker:Favorite', 'Add');
        $builder->addRoute('torrents/favorite/remove/{info_hash}', 'torrents/favorite/remove/{info_hash}', 'Harment\XBTTracker:Favorite', 'Remove');
        
        // مسارات TMDB
        $builder->addRoute('torrents/tmdb/search', 'torrents/tmdb/search', 'Harment\XBTTracker:Tmdb', 'Search');
        $builder->addRoute('torrents/tmdb/info/{tmdb_id}', 'torrents/tmdb/info/{tmdb_id}', 'Harment\XBTTracker:Tmdb', 'Info');
        
        // مسارات صفحات ملف المستخدم
        $builder->addRoute('torrents/user-torrents', 'torrents/user-torrents', 'Harment\XBTTracker:UserTorrents', 'List');
        $builder->addRoute('torrents/bonus-points', 'torrents/bonus-points', 'Harment\XBTTracker:BonusPoints', 'View');
        $builder->addRoute('torrents/bonus-points/redeem', 'torrents/bonus-points/redeem', 'Harment\XBTTracker:BonusPoints', 'Redeem');
        $builder->addRoute('torrents/warnings', 'torrents/warnings', 'Harment\XBTTracker:Warnings', 'View');
        $builder->addRoute('torrents/warnings/remove/{warning_id}', 'torrents/warnings/remove/{warning_id}', 'Harment\XBTTracker:Warnings', 'Remove');
        
        // مسارات تصفح الفئات
        $builder->addRoute('torrents/category/{category_id}', 'torrents/category/{category_id}', 'Harment\XBTTracker:Category', 'View');
        
        // مسارات البحث
        $builder->addRoute('torrents/search', 'torrents/search', 'Harment\XBTTracker:Search', 'Index');
        $builder->addRoute('torrents/search/advanced', 'torrents/search/advanced', 'Harment\XBTTracker:Search', 'Advanced');
        
        // مسارات الإعلان واستكشاف المعلومات
        $builder->addRoute('torrents/announce', 'torrents/announce', 'Harment\XBTTracker:Tracker', 'Announce');
        $builder->addRoute('torrents/scrape', 'torrents/scrape', 'Harment\XBTTracker:Tracker', 'Scrape');
        
        // مسارات إحصائيات التراكر
        $builder->addRoute('torrents/stats', 'torrents/stats', 'Harment\XBTTracker:Tracker', 'Stats');
    }

    /**
     * Build admin routes
     *
     * @param RouteBuilderInterface $builder Route builder
     * @return void
     */
    protected function buildAdminRoutes(RouteBuilderInterface $builder)
    {
        // لوحة القيادة الرئيسية للتراكر
        $builder->addRoute('tracker', 'tracker', 'Harment\XBTTracker:Admin', 'Index');
        
        // إدارة التورنتات
        $builder->addRoute('tracker/torrents', 'tracker/torrents', 'Harment\XBTTracker:Torrents', 'List');
        $builder->addRoute('tracker/torrents/view/{torrent_id}', 'tracker/torrents/view/{torrent_id}', 'Harment\XBTTracker:Torrents', 'View');
        $builder->addRoute('tracker/torrents/edit/{torrent_id}', 'tracker/torrents/edit/{torrent_id}', 'Harment\XBTTracker:Torrents', 'Edit');
        $builder->addRoute('tracker/torrents/save/{torrent_id}', 'tracker/torrents/save/{torrent_id}', 'Harment\XBTTracker:Torrents', 'Save');
        $builder->addRoute('tracker/torrents/delete/{torrent_id}', 'tracker/torrents/delete/{torrent_id}', 'Harment\XBTTracker:Torrents', 'Delete');
        $builder->addRoute('tracker/torrents/delete-confirm/{torrent_id}', 'tracker/torrents/delete-confirm/{torrent_id}', 'Harment\XBTTracker:Torrents', 'DeleteConfirm');
        $builder->addRoute('tracker/torrents/bulk', 'tracker/torrents/bulk', 'Harment\XBTTracker:Torrents', 'Bulk');
        
        // إدارة المستخدمين
        $builder->addRoute('tracker/users', 'tracker/users', 'Harment\XBTTracker:User', 'List');
        $builder->addRoute('tracker/users/details/{user_id}', 'tracker/users/details/{user_id}', 'Harment\XBTTracker:User', 'Details');
        $builder->addRoute('tracker/users/edit/{user_id}', 'tracker/users/edit/{user_id}', 'Harment\XBTTracker:User', 'Edit');
        $builder->addRoute('tracker/users/edit-save/{user_id}', 'tracker/users/edit-save/{user_id}', 'Harment\XBTTracker:User', 'EditSave');
        $builder->addRoute('tracker/users/delete/{user_id}', 'tracker/users/delete/{user_id}', 'Harment\XBTTracker:User', 'Delete');
        $builder->addRoute('tracker/users/bulk-action', 'tracker/users/bulk-action', 'Harment\XBTTracker:User', 'BulkAction');
        
        // إدارة إحصائيات المستخدمين
        $builder->addRoute('tracker/user-stats', 'tracker/user-stats', 'Harment\XBTTracker:UserStats', 'Index');
        $builder->addRoute('tracker/user-stats/edit/{user_id}', 'tracker/user-stats/edit/{user_id}', 'Harment\XBTTracker:UserStats', 'Edit');
        $builder->addRoute('tracker/user-stats/save/{user_id}', 'tracker/user-stats/save/{user_id}', 'Harment\XBTTracker:UserStats', 'Save');
        $builder->addRoute('tracker/user-stats/reset-passkey/{user_id}', 'tracker/user-stats/reset-passkey/{user_id}', 'Harment\XBTTracker:UserStats', 'ResetPasskey');
        $builder->addRoute('tracker/user-stats/reset-passkey-confirm/{user_id}', 'tracker/user-stats/reset-passkey-confirm/{user_id}', 'Harment\XBTTracker:UserStats', 'ResetPasskeyConfirm');
        $builder->addRoute('tracker/user-stats/bulk', 'tracker/user-stats/bulk', 'Harment\XBTTracker:UserStats', 'Bulk');
        
        // إدارة التحذيرات
        $builder->addRoute('tracker/warnings', 'tracker/warnings', 'Harment\XBTTracker:Warnings', 'List');
        $builder->addRoute('tracker/warnings/issue', 'tracker/warnings/issue', 'Harment\XBTTracker:Warnings', 'Issue');
        $builder->addRoute('tracker/warnings/issue-save', 'tracker/warnings/issue-save', 'Harment\XBTTracker:Warnings', 'IssueSave');
        $builder->addRoute('tracker/warnings/details/{warning_id}', 'tracker/warnings/details/{warning_id}', 'Harment\XBTTracker:Warnings', 'Details');
        $builder->addRoute('tracker/warnings/remove/{warning_id}', 'tracker/warnings/remove/{warning_id}', 'Harment\XBTTracker:Warnings', 'Remove');
        $builder->addRoute('tracker/warnings/delete/{warning_id}', 'tracker/warnings/delete/{warning_id}', 'Harment\XBTTracker:Warnings', 'Delete');
        $builder->addRoute('tracker/warnings/bulk-action', 'tracker/warnings/bulk-action', 'Harment\XBTTracker:Warnings', 'BulkAction');
        $builder->addRoute('tracker/warnings/bulk-remove', 'tracker/warnings/bulk-remove', 'Harment\XBTTracker:Warnings', 'BulkRemove');
        $builder->addRoute('tracker/warnings/bulk-delete', 'tracker/warnings/bulk-delete', 'Harment\XBTTracker:Warnings', 'BulkDelete');
        
        // إدارة الفئات
        $builder->addRoute('tracker/categories', 'tracker/categories', 'Harment\XBTTracker:Category', 'Index');
        $builder->addRoute('tracker/categories/add', 'tracker/categories/add', 'Harment\XBTTracker:Category', 'Add');
        $builder->addRoute('tracker/categories/save', 'tracker/categories/save', 'Harment\XBTTracker:Category', 'Save');
        $builder->addRoute('tracker/categories/edit/{category_id}', 'tracker/categories/edit/{category_id}', 'Harment\XBTTracker:Category', 'Edit');
        $builder->addRoute('tracker/categories/save-edit/{category_id}', 'tracker/categories/save-edit/{category_id}', 'Harment\XBTTracker:Category', 'SaveEdit');
        $builder->addRoute('tracker/categories/delete/{category_id}', 'tracker/categories/delete/{category_id}', 'Harment\XBTTracker:Category', 'Delete');
        $builder->addRoute('tracker/categories/delete-confirm/{category_id}', 'tracker/categories/delete-confirm/{category_id}', 'Harment\XBTTracker:Category', 'DeleteConfirm');
        $builder->addRoute('tracker/categories/sort', 'tracker/categories/sort', 'Harment\XBTTracker:Category', 'Sort');
        
        // إدارة نقاط المكافآت
        $builder->addRoute('tracker/bonus-points', 'tracker/bonus-points', 'Harment\XBTTracker:BonusPoints', 'List');
        $builder->addRoute('tracker/bonus-points/all', 'tracker/bonus-points/all', 'Harment\XBTTracker:BonusPoints', 'All');
        $builder->addRoute('tracker/bonus-points/award', 'tracker/bonus-points/award', 'Harment\XBTTracker:BonusPoints', 'Award');
        $builder->addRoute('tracker/bonus-points/award-all', 'tracker/bonus-points/award-all', 'Harment\XBTTracker:BonusPoints', 'AwardAll');
        $builder->addRoute('tracker/bonus-points/award-by-ratio', 'tracker/bonus-points/award-by-ratio', 'Harment\XBTTracker:BonusPoints', 'AwardByRatio');
        $builder->addRoute('tracker/bonus-points/adjust/{user_id}', 'tracker/bonus-points/adjust/{user_id}', 'Harment\XBTTracker:BonusPoints', 'Adjust');
        $builder->addRoute('tracker/bonus-points/history/{user_id}', 'tracker/bonus-points/history/{user_id}', 'Harment\XBTTracker:BonusPoints', 'History');
        $builder->addRoute('tracker/bonus-points/log', 'tracker/bonus-points/log', 'Harment\XBTTracker:BonusPoints', 'Log');
        $builder->addRoute('tracker/bonus-points/reset-all', 'tracker/bonus-points/reset-all', 'Harment\XBTTracker:BonusPoints', 'ResetAll');
        
        // إدارة الإعلانات
        $builder->addRoute('tracker/announcements', 'tracker/announcements', 'Harment\XBTTracker:Announcements', 'List');
        $builder->addRoute('tracker/announcements/add', 'tracker/announcements/add', 'Harment\XBTTracker:Announcements', 'Add');
        $builder->addRoute('tracker/announcements/add-save', 'tracker/announcements/add-save', 'Harment\XBTTracker:Announcements', 'AddSave');
        $builder->addRoute('tracker/announcements/details/{announcement_id}', 'tracker/announcements/details/{announcement_id}', 'Harment\XBTTracker:Announcements', 'Details');
        $builder->addRoute('tracker/announcements/edit/{announcement_id}', 'tracker/announcements/edit/{announcement_id}', 'Harment\XBTTracker:Announcements', 'Edit');
        $builder->addRoute('tracker/announcements/edit-save/{announcement_id}', 'tracker/announcements/edit-save/{announcement_id}', 'Harment\XBTTracker:Announcements', 'EditSave');
        $builder->addRoute('tracker/announcements/delete/{announcement_id}', 'tracker/announcements/delete/{announcement_id}', 'Harment\XBTTracker:Announcements', 'Delete');
        $builder->addRoute('tracker/announcements/toggle/{announcement_id}', 'tracker/announcements/toggle/{announcement_id}', 'Harment\XBTTracker:Announcements', 'Toggle');
        
        // سجلات النظام
        $builder->addRoute('tracker/logs', 'tracker/logs', 'Harment\XBTTracker:Logs', 'List');
        $builder->addRoute('tracker/logs/export', 'tracker/logs/export', 'Harment\XBTTracker:Logs', 'Export');
        $builder->addRoute('tracker/logs/clear', 'tracker/logs/clear', 'Harment\XBTTracker:Logs', 'Clear');
        $builder->addRoute('tracker/logs/test-tmdb-api', 'tracker/logs/test-tmdb-api', 'Harment\XBTTracker:Logs', 'TestTmdbApi');
        $builder->addRoute('tracker/logs/test-mail-settings', 'tracker/logs/test-mail-settings', 'Harment\XBTTracker:Logs', 'TestMailSettings');
        
        // الإعدادات
        $builder->addRoute('tracker/settings', 'tracker/settings', 'Harment\XBTTracker:Settings', 'Index');
        $builder->addRoute('tracker/settings/update', 'tracker/settings/update', 'Harment\XBTTracker:Settings', 'Update');
        $builder->addRoute('tracker/settings/create-backup', 'tracker/settings/create-backup', 'Harment\XBTTracker:Settings', 'CreateBackup');
        $builder->addRoute('tracker/settings/restore-backup', 'tracker/settings/restore-backup', 'Harment\XBTTracker:Settings', 'RestoreBackup');
    }
}