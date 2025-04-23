<?php

namespace Harment\XBTTracker\BbCode;

use XF\BbCode\Renderer\AbstractRenderer;

class Torrent
{
    /**
     * Render the [TORRENT] BB code
     *
     * @param array $tag Tag data
     * @param array $options Rendering options
     * @param string $name Tag name
     * @param string|null $option Tag option (info hash)
     * @param string $content Content between tags
     * @param AbstractRenderer $renderer The BB code renderer
     * 
     * @return string HTML
     */
    public static function render(array $tag, array $options, $name, $option, $content, AbstractRenderer $renderer)
    {
        // الحصول على معرف التورنت من المعلمات
        $infoHash = $option;
        
        if (!$infoHash)
        {
            return '<div class="blockMessage blockMessage--error">' . \XF::phrase('harment_xbttracker_no_info_hash_provided') . '</div>';
        }
        
        // الحصول على معلومات التورنت من قاعدة البيانات
        $info = self::getTorrentInfo($infoHash);
        
        if (!$info)
        {
            return '<div class="blockMessage blockMessage--error">' . \XF::phrase('harment_xbttracker_torrent_not_found') . '</div>';
        }
        
        // تهيئة المتغيرات لقالب المشاهدة
        $app = \XF::app();
        $templater = $app->templater();
        
        // تحضير المتغيرات للعرض
        $viewParams = [
            'info' => $info,
            'display_style' => isset($options['displayStyle']) ? $options['displayStyle'] : 'simple'
        ];
        
        // عرض المحتوى باستخدام قالب
        return $templater->renderTemplate('public:harment_xbttracker_bb_code_torrent', $viewParams);
    }
    
    /**
     * الحصول على معلومات التورنت من قاعدة البيانات
     *
     * @param string $infoHash
     * @return array|null
     */
    protected static function getTorrentInfo($infoHash)
    {
        $db = \XF::db();
        $torrentInfo = $db->fetchRow('
            SELECT *
            FROM xf_xbt_torrents
            WHERE info_hash = ?
        ', [$infoHash]);
        
        if (!$torrentInfo)
        {
            return null;
        }
        
        return [
            'torrent_id' => $torrentInfo['torrent_id'],
            'name' => $torrentInfo['title'],
            'info_hash' => $torrentInfo['info_hash'],
            'size' => $torrentInfo['size'],
            'seeders' => $torrentInfo['seeders'],
            'leechers' => $torrentInfo['leechers'],
            'completed' => $torrentInfo['completed'],
            'category_id' => $torrentInfo['category_id'],
            'creation_date' => $torrentInfo['creation_date']
        ];
    }
}