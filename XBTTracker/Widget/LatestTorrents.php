<?php
// src/addons/XBTTracker/Widget/LatestTorrents.php
namespace XBTTracker\Widget;

use XF\Widget\AbstractWidget;

/**
 * Widget to display the latest torrents
 */
class LatestTorrents extends AbstractWidget
{
    /**
     * Get default options for this widget
     *
     * @return array
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
     * @return string|array
     */
    public function render()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->hasPermission('xbtTracker', 'view')) {
            return '';
        }
        
        /** @var \XBTTracker\Repository\Torrent $torrentRepo */
        $torrentRepo = $this->repository('XBTTracker:Torrent');
        
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
            'widgetStyle' => $this->style
        ];
        
        return $this->renderer('xbt_widget_latest_torrents', $viewParams);
    }
    
    /**
     * Get the admin options template
     *
     * @return string
     */
    public function getOptionsTemplate()
    {
        /** @var \XBTTracker\Repository\Category $categoryRepo */
        $categoryRepo = $this->repository('XBTTracker:Category');
        $categoryOptions = $categoryRepo->getCategoryOptions();
        
        return $this->renderer('admin:xbt_widget_latest_torrents_options', [
            'options' => $this->options,
            'categoryOptions' => $categoryOptions
        ]);
    }
}