<?php
// src/addons/Harment/XBTTracker/Widget/LatestTorrents.php
namespace Harment\XBTTracker\Widget;

use XF\Widget\AbstractWidget;

/**
 * Widget to display the latest torrents
 * 
 * @package Harment\XBTTracker\Widget
 */
class LatestTorrents extends AbstractWidget
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
        $style = $this->options['style'];
        
        $finder = $torrentRepo->findLatestTorrents($limit);
        
        if ($categoryId) {
            $finder->where('category_id', $categoryId);
        }
        
        $torrents = $finder->fetch();
        
        $viewParams = [
            'torrents' => $torrents,
            'categoryId' => $categoryId,
            'style' => $style,
            'widgetStyle' => $this->style,
            'limit' => $limit
        ];
        
        return $this->renderer('xbt_widget_latest_torrents', $viewParams);
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
        
        return $this->renderer('admin:xbt_widget_latest_torrents_options', [
            'options' => $this->options,
            'categoryOptions' => $categoryOptions
        ]);
    }
}