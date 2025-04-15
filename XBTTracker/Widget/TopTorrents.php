<?php
// src/addons/XBTTracker/Widget/TopTorrents.php
namespace XBTTracker\Widget;

use XF\Widget\AbstractWidget;

/**
 * Widget to display top torrents in various categories
 */
class TopTorrents extends AbstractWidget
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
            'display_type' => 'downloaded', // downloaded, seeders, viewed
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
        $displayType = $this->options['display_type'];
        $style = $this->options['style'];
        
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
            'categoryId' => $categoryId
        ];
        
        return $this->renderer('xbt_widget_top_torrents', $viewParams);
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
        
        return $this->renderer('admin:xbt_widget_top_torrents_options', [
            'options' => $this->options,
            'categoryOptions' => $categoryOptions
        ]);
    }
}