<?php
// src/addons/Harment/XBTTracker/Widget/TopTorrents.php
namespace Harment\XBTTracker\Widget;

use XF\Widget\AbstractWidget;

/**
 * Widget to display top torrents in various categories
 * 
 * @package Harment\XBTTracker\Widget
 */
class TopTorrents extends AbstractWidget
{
    /**
     * Get default options for this widget
     *
     * @return array Default widget options
     */
    protected function getDefaultOptions()
    {
        return [
            'limit' => 10,
            'category_id' => 0,
            'display_type' => 'downloaded', // downloaded, seeders, viewed
            'style' => 'list' // list, grid
        ];
    }
    
    /**
     * Render the widget
     *
     * @return string|array Rendered widget content
     */
    public function render()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->hasPermission('xbtTracker', 'view')) {
            return '';
        }
        
        /** @var \Harment\XBTTracker\Repository\Torrent $torrentRepo */
        $torrentRepo = $this->repository('Harment\XBTTracker:Torrent');
        
        $limit = max(1, intval($this->options['limit']));
        $categoryId = intval($this->options['category_id']);
        $displayType = in_array($this->options['display_type'], ['seeders', 'viewed', 'downloaded']) 
            ? $this->options['display_type'] 
            : 'downloaded';
        $style = in_array($this->options['style'], ['list', 'grid']) 
            ? $this->options['style'] 
            : 'list';
        
        switch ($displayType) {
            case 'seeders':
                $finder = $torrentRepo->findMostSeededTorrents($limit);
                break;
            case 'viewed':
                $finder = $torrentRepo->findMostViewedTorrents($limit);
                break;
            case 'downloaded':
            default:
                $finder = $torrentRepo->findMostDownloadedTorrents($limit);
        }
        
        if ($categoryId) {
            $finder->where('category_id', $categoryId);
        }
        
        $torrents = $finder->fetch();
        
        $viewParams = [
            'torrents' => $torrents,
            'displayType' => $displayType,
            'style' => $style,
            'widgetStyle' => $this->style,
            'categoryId' => $categoryId,
            'limit' => $limit
        ];
        
        return $this->renderer('xbt_widget_top_torrents', $viewParams);
    }
    
    /**
     * Get the admin options template
     *
     * @return string Admin options template name
     */
    public function getOptionsTemplate()
    {
        /** @var \Harment\XBTTracker\Repository\Category $categoryRepo */
        $categoryRepo = $this->repository('Harment\XBTTracker:Category');
        $categoryOptions = $categoryRepo->getCategoryOptions();
        
        return $this->renderer('admin:xbt_widget_top_torrents_options', [
            'options' => $this->options,
            'categoryOptions' => $categoryOptions
        ]);
    }
}